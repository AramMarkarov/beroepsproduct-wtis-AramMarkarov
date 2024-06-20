<?php
session_start();
require_once 'db_connectie.php';

// Check of de gebruiker een medewerker is
if (!isset($_SESSION['employee'])) {
    header('Location: login.php');
    exit();
}

// De medewerker account bevat een balienummer
$employeeCounter = $_SESSION['employee'];

// Variabelen
$error = '';
$flights = [];

// Filter variababelen
$filterDestination = $_GET['luchthaven'] ?? '';
$filterDate = $_GET['datum'] ?? '';
$filterDeparted = isset($_GET['departed']) && $_GET['departed'] === 'no';

// Sorteer variabel
$sortDeparture = $_GET['sort'] ?? 'asc';

// Query voor vluchten op de website. CONVERT gebruikt om op web een String te weergeven
$sql = "SELECT v.vluchtnummer, v.bestemming, CONVERT(VARCHAR, v.vertrektijd, 120) AS vertrektijd, iv.balienummer, v.gatecode, l.naam AS luchthaven_naam
        FROM vlucht v
        JOIN luchthaven l ON v.bestemming = l.luchthavencode
        JOIN IncheckenVlucht iv ON v.vluchtnummer = iv.vluchtnummer";

// Array om parameters op te slaan, later voor binden
$params = [];

// Filters toegepast als query toevoegingen
if (!empty($filterDestination)) {
    // Filter query toepassing met een LIKE clause om inpreciese data te kunnen zien
    $sql .= " WHERE l.naam LIKE :destination";
    $params[':destination'] = '%' . $filterDestination . '%';
}

if (!empty($filterDate)) {
    // Check of een WHERE al aanwezig is (door voorgaande queries)
    if (!str_contains($sql, 'WHERE')) {
        $sql .= " WHERE";
    } else {
        $sql .= " AND";
    }
    // Filter voor vertrektijd
    $sql .= " CONVERT(DATE, v.vertrektijd) = :filterDate";
    $params[':filterDate'] = $filterDate;
}

if (!$filterDeparted) {
    // Check of een WHERE al aanwezig is (door voorgaande queries)
    if (!str_contains($sql, 'WHERE')) {
        $sql .= " WHERE";
    } else {
        $sql .= " AND";
    }
    // Sorteer conditie voor vertrokken vluchten
    $sql .= " v.vertrektijd >= GETDATE()";
}

// Toevoeging van sorteer conditie
if ($sortDeparture === 'asc') {
    $sql .= " ORDER BY v.vertrektijd ASC";
} else {
    $sql .= " ORDER BY v.vertrektijd DESC";
}

// Hier is het veilig meteen de DB te raadplegen omdat een baliemedewerker moet ingelogd zijn
$db = maakVerbinding();

if ($db) {
    try {
        $stmt = $db->prepare($sql);
        // Dynamisch parameters binden
        foreach ($params as $param => $value) {
            $stmt->bindParam($param, $value);
        }

        // Execute query voor vluchten zoeken
        if ($stmt->execute()) {
            $flights = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            // Error catching tijdens de query
            $errorInfo = $stmt->errorInfo();
            $error = "SQL error: " . $errorInfo[2];
        }
    } catch (PDOException $e) {
        // Error catching
        $error = "Error fetching flights: " . $e->getMessage();
    }
} else {
    // Error handling
    $error = "Database connection failed.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <?php include 'includes/tailwind_script.php'; ?>
</head>
<body class="bg-background text-text text-body">

<!-- Navigation Bar Include -->
<?php include 'includes/nav.php'; ?>

<div class="mx-[10%] w-[80%] py-8">
    <div class="grid grid-cols-1 gap-8">

        <section id="flights" class="bg-nav p-6 rounded-lg shadow-lg h-full flex flex-col">
            <h2 class="text-2xl font-bold mb-4">All Flights</h2>
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="GET" class="flex flex-col gap-4">
                <div class="flex gap-4">
                    <div class="flex-grow">
                        <label for="destination" class="block text-sm font-medium text-gray-700">Destination</label>
                        <input type="text" id="destination" name="luchthaven" value="<?php echo htmlspecialchars($filterDestination); ?>" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                    </div>
                    <div class="flex-grow">
                        <label for="departure-date" class="block text-sm font-medium text-gray-700">Departure Date</label>
                        <input type="date" id="departure-date" name="datum" value="<?php echo htmlspecialchars($filterDate); ?>" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                    </div>
                </div>
                <div class="flex items-center mt-4">
                    <input type="checkbox" id="departed" name="departed" value="no" <?php if (!$filterDeparted) echo 'checked'; ?> class="rounded border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                    <label for="departed" class="ml-2 block text-sm text-gray-900">Include Departed Flights</label>
                </div>
                <div class="mt-4">
                    <label for="sort" class="block text-sm font-medium text-gray-700">Sort by Departure Time</label>
                    <select id="sort" name="sort" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                        <option value="asc" <?php if ($sortDeparture === 'asc') echo 'selected'; ?>>Ascending</option>
                        <option value="desc" <?php if ($sortDeparture === 'desc') echo 'selected'; ?>>Descending</option>
                    </select>
                </div>
                <button type="submit" class="w-full py-2 px-4 text-white hover:text-text bg-button hover:bg-hover rounded-md transition duration-150 ease-in-out mt-4">Apply Filters</button>
            </form>

            <?php if (!empty($error)): ?>
                <p class="text-red-500 mt-4"><?php echo htmlspecialchars($error); ?></p>
            <?php endif; ?>

            <?php if (!empty($flights)): ?>
                <table class="table-auto w-full mt-8">
                    <thead>
                    <tr>
                        <th class="px-4 py-2">Flight Number</th>
                        <th class="px-4 py-2">Destination</th>
                        <th class="px-4 py-2">Departure Time</th>
                        <th class="px-4 py-2">Counter Number</th>
                        <th class="px-4 py-2">Gate Code</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($flights as $flight): ?>
                        <tr>
                            <td class="border px-4 py-2"><?php echo htmlspecialchars($flight['vluchtnummer']); ?></td>
                            <td class="border px-4 py-2"><?php echo htmlspecialchars($flight['luchthaven_naam']); ?></td>
                            <td class="border px-4 py-2"><?php echo htmlspecialchars($flight['vertrektijd']); ?></td>
                            <td class="border px-4 py-2"><?php echo htmlspecialchars($flight['balienummer']); ?></td>
                            <td class="border px-4 py-2"><?php echo htmlspecialchars($flight['gatecode']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="text-gray-500 mt-4">No flights found.</p>
            <?php endif; ?>
        </section>

    </div>
</div>

<div class="min-h-screen"></div>
<!-- Footer Include -->
<?php include 'includes/footer.php'; ?>

</body>
</html>
