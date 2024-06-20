<?php
session_start();
require_once 'db_connectie.php';

// Medewerker check
if (!isset($_SESSION['employee'])) {
    header('Location: employeelogin.php');
    exit();
}

$db = maakVerbinding();

// Variabelen
$flights = [];
$passengers = [];
$error = '';
$success = '';

if ($db) {
    // Fetch alle toekomstige vluchten in een dropdown
    $stmt = $db->prepare("SELECT vluchtnummer, bestemming, vertrektijd FROM Vlucht WHERE vertrektijd > GETDATE()");
    $stmt->execute();
    $flights = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Handle form wanneer een POST verzoek is voor een zoekaanvraag
    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        if (isset($_POST['selectedFlight'])) {
            // Fetch passagiers voor de geselecteerde vlucht
            $selectedFlight = $_POST['selectedFlight'];
            $stmt = $db->prepare("
                SELECT DISTINCT p.passagiernummer, p.naam, p.stoel, v.bestemming, v.gatecode, iv.balienummer, v.vertrektijd
                FROM Passagier p
                JOIN Vlucht v ON p.vluchtnummer = v.vluchtnummer
                LEFT JOIN IncheckenVlucht iv ON v.vluchtnummer = iv.vluchtnummer
                WHERE p.vluchtnummer = :vluchtnummer
            ");
            $stmt->bindParam(':vluchtnummer', $selectedFlight);
            $stmt->execute();
            $passengers = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Fetch luggage incheck info voor iedere passagier
            foreach ($passengers as &$passenger) {
                $stmt = $db->prepare("SELECT COUNT(*) AS luggage_count FROM BagageObject WHERE passagiernummer = :passagiernummer");
                $stmt->bindParam(':passagiernummer', $passenger['passagiernummer']);
                $stmt->execute();
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $passenger['luggage'] = $result['luggage_count'] > 0 ? 'Yes' : 'No';
            }
        } elseif (isset($_POST['searchPassenger'])) {
            // Zoeken voor passagiernaam, query bevat elke vlucht gegevens op gezochte naam
            $searchPassenger = $_POST['searchPassenger'];
            $stmt = $db->prepare("
                SELECT DISTINCT p.passagiernummer, p.naam, p.vluchtnummer, p.stoel, v.bestemming, v.gatecode, iv.balienummer, v.vertrektijd
                FROM Passagier p
                JOIN Vlucht v ON p.vluchtnummer = v.vluchtnummer
                LEFT JOIN IncheckenVlucht iv ON v.vluchtnummer = iv.vluchtnummer
                WHERE p.naam LIKE :search
            ");
            $searchParam = '%' . $searchPassenger . '%';
            $stmt->bindParam(':search', $searchParam);
            $stmt->execute();
            $passengers = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Fetch bagage check-in informatie
            foreach ($passengers as &$passenger) {
                $stmt = $db->prepare("SELECT COUNT(*) AS luggage_count FROM BagageObject WHERE passagiernummer = :passagiernummer");
                $stmt->bindParam(':passagiernummer', $passenger['passagiernummer']);
                $stmt->execute();
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $passenger['luggage'] = $result['luggage_count'] > 0 ? 'Yes' : 'No';
            }

            if (empty($passengers)) {
                $error = "No passengers found matching your search criteria.";
            }
        }
    }
} else {
    $error = "Database connection failed.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Passenger Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <?php include 'includes/tailwind_script.php'; ?>
</head>
<body class="bg-background text-text text-body">

<!-- Navigation Bar Include -->
<?php include 'includes/nav.php'; ?>

<!-- Main Content -->
<div class="mx-[10%] w-[80%] py-8">
    <div class="bg-nav p-6 rounded-lg shadow-lg">
        <h2 class="text-2xl font-bold mb-4">Passenger Management</h2>
        <?php if (!empty($error)): ?>
            <p class="text-red-500 mb-4"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <p class="text-green-500 mb-4"><?php echo htmlspecialchars($success); ?></p>
        <?php endif; ?>
        <div class="mb-4">
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" class="space-y-4">
                <div>
                    <label for="selectedFlight" class="block text-sm font-medium text-gray-700">Select Flight</label>
                    <select id="selectedFlight" name="selectedFlight" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                        <option value="">Select Flight Number</option>
                        <?php foreach ($flights as $flight): ?>
                            <option value="<?php echo htmlspecialchars($flight['vluchtnummer']); ?>"><?php echo htmlspecialchars($flight['vluchtnummer'] . ' - ' . $flight['bestemming'] . ' (' . date('Y-m-d H:i', strtotime($flight['vertrektijd'])) . ')'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex justify-end">
                    <button type="submit" class="w-full py-2 px-4 text-white hover:text-text bg-button hover:bg-hover rounded-md transition duration-150 ease-in-out">View Passengers</button>
                </div>
            </form>
        </div>
        <div class="mb-4">
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" class="space-y-4">
                <div>
                    <label for="searchPassenger" class="block text-sm font-medium text-gray-700">Search Passenger</label>
                    <input type="text" id="searchPassenger" name="searchPassenger" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" placeholder="Enter passenger name">
                </div>
                <div class="flex justify-end">
                    <button type="submit" class="w-full py-2 px-4 text-white hover:text-text bg-button hover:bg-hover rounded-md transition duration-150 ease-in-out">Search</button>
                </div>
            </form>
        </div>
        <?php if (!empty($passengers)): ?>
            <div class="mt-4">
                <h3 class="text-xl font-bold mb-4">Passengers</h3>
                <table class="w-full table-auto">
                    <thead>
                    <tr class="bg-gray-100">
                        <th class="px-4 py-2">Passenger ID</th>
                        <th class="px-4 py-2">Name</th>
                        <th class="px-4 py-2">Seat</th>
                        <th class="px-4 py-2">Destination</th>
                        <th class="px-4 py-2">Gate</th>
                        <th class="px-4 py-2">Check-in Desk</th>
                        <th class="px-4 py-2">Luggage Checked In</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($passengers as $passenger): ?>
                        <tr>
                            <td class="border px-4 py-2"><?php echo htmlspecialchars($passenger['passagiernummer']); ?></td>
                            <td class="border px-4 py-2"><?php echo htmlspecialchars($passenger['naam']); ?></td>
                            <td class="border px-4 py-2"><?php echo htmlspecialchars($passenger['stoel']); ?></td>
                            <td class="border px-4 py-2"><?php echo htmlspecialchars($passenger['bestemming']); ?></td>
                            <td class="border px-4 py-2"><?php echo htmlspecialchars($passenger['gatecode']); ?></td>
                            <td class="border px-4 py-2"><?php echo htmlspecialchars($passenger['balienummer']); ?></td>
                            <td class="border px-4 py-2"><?php echo htmlspecialchars($passenger['luggage']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="min-h-screen"></div>
<!-- Footer Include -->
<?php include 'includes/footer.php'; ?>

</body>
</html>
