<?php
session_start();
require_once 'db_connectie.php';

// Variabelen
$error = '';
$flightDetails = [];

// Check als een form opgestuurd word
if ($_SERVER["REQUEST_METHOD"] === "GET" && isset($_GET['flightNumber'])) {
    $flightNumber = $_GET['flightNumber'];

    $db = maakVerbinding();

    if ($db) {
        $stmt = $db->prepare(
            "SELECT v.vluchtnummer, v.bestemming, v.vertrektijd, iv.balienummer, v.gatecode, l.naam AS luchthaven_naam
             FROM vlucht v
             JOIN luchthaven l ON v.bestemming = l.luchthavencode
             JOIN IncheckenVlucht iv ON v.vluchtnummer = iv.vluchtnummer
             WHERE v.vluchtnummer = :flightNumber AND v.vertrektijd > CURRENT_TIMESTAMP"
        );
        $stmt->bindParam(':flightNumber', $flightNumber);

        // Debugging: Error logging
        try {
            if ($stmt->execute()) {
                $flightDetails = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if (empty($flightDetails)) {
                    $error = "No future flights found for this flight number.";
                }
            } else {
                // Log SQL error
                $errorInfo = $stmt->errorInfo();
                $error = "SQL error: " . $errorInfo[2];
            }
        } catch (PDOException $e) {
            $error = "An issue occurred during the search of the flight: " . $e->getMessage();
        }
    } else {
        $error = "Database connection failed.";
    }
}

// Format the datetime for display
if (!empty($flightDetails)) {
    try {
        $datetime = new DateTime($flightDetails[0]['vertrektijd']);
        $formattedTime = $datetime->format('d/m/Y H:i');
    } catch (Exception $e) {
        $error = "An error occurred while processing the departure time: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <script src="https://cdn.tailwindcss.com"></script>
    <title>Employee Dashboard</title>

    <!-- Tailwind CSS script -->
    <?php include 'includes/tailwind_script.php'; ?>
</head>
<body class="bg-background text-text text-body">
<!-- Navigation Bar Include -->
<?php include 'includes/nav.php'; ?>

<!-- Main Content -->
<div class="mx-[20%] w-[60%] flex flex-wrap justify-center gap-8 mt-4 mb-4">
    <!-- Flight Information -->
    <section id="flights" class="bg-nav p-6 rounded-lg w-[48%]">
        <h2 class="text-2xl font-bold mb-4">Flight Information</h2>
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="get">
            <div class="mb-4">
                <label for="flightNumber" class="block text-sm font-medium text-gray-700">Flight Number</label>
                <input type="text" id="flightNumber" name="flightNumber" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
            </div>
            <div class="flex justify-end">
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md">Retrieve Flight Info</button>
            </div>
        </form>

        <?php if (!empty($error)): ?>
            <p class="text-red-500 mt-4"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>

        <?php if (!empty($flightDetails)): ?>
            <div class="mt-8 p-6 rounded-lg w-full max-w-lg">
                <table class="table-auto w-full">
                    <thead>
                    <tr>
                        <th class="px-4 py-2">Vluchtnummer</th>
                        <th class="px-4 py-2">Bestemming</th>
                        <th class="px-4 py-2">Vertrektijd</th>
                        <th class="px-4 py-2">Balie</th>
                        <th class="px-4 py-2">Gate</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($flightDetails as $flight): ?>
                        <tr>
                            <td class="border px-4 py-2"><?php echo htmlspecialchars($flight['vluchtnummer']); ?></td>
                            <td class="border px-4 py-2"><?php echo htmlspecialchars($flight['luchthaven_naam']); ?></td>
                            <td class="border px-4 py-2"><?php echo htmlspecialchars($formattedTime); ?></td>
                            <td class="border px-4 py-2"><?php echo htmlspecialchars($flight['balienummer']); ?></td>
                            <td class="border px-4 py-2"><?php echo htmlspecialchars($flight['gatecode']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>