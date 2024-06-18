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
<div class="mx-[10%] w-[80%] py-8">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
        <!-- Flight Information -->
        <section id="flights" class="bg-nav p-6 rounded-lg shadow-lg h-full flex flex-col">
            <h2 class="text-2xl font-bold mb-4">Flight Information</h2>
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" class="flex flex-col flex-grow">
                <div class="mb-4 flex-grow">
                    <label for="flight-number" class="block text-sm font-medium text-gray-700">Flight Number</label>
                    <input type="text" id="flight-number" name="flight-number" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                </div>
                <div class="flex justify-end">
                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md">Retrieve Flight Info</button>
                </div>
            </form>
            <?php if (!empty($error)): ?>
                <p class="text-red-500 mt-4"><?php echo htmlspecialchars($error); ?></p>
            <?php endif; ?>
            <?php if (!empty($flightDetails)): ?>
                <table class="table-auto w-full max-w-lg mt-8">
                    <thead>
                    <tr>
                        <th class="px-4 py-2">Flight Number</th>
                        <th class="px-4 py-2">Destination</th>
                        <th class="px-4 py-2">Departure Time</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($flightDetails as $flight): ?>
                        <tr>
                            <td class="border px-4 py-2"><?php echo htmlspecialchars($flight['vluchtnummer']); ?></td>
                            <td class="border px-4 py-2"><?php echo htmlspecialchars($flight['luchthaven_naam']); ?></td>
                            <td class="border px-4 py-2"><?php echo htmlspecialchars($formattedTime); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>

        <!-- Flight Overview -->
        <section id="flight-overview" class="bg-nav p-6 rounded-lg shadow-lg h-full flex flex-col">
            <h2 class="text-2xl font-bold mb-4">Flight Overview</h2>
            <div class="flex mb-4">
                <button class="bg-blue-600 text-white px-4 py-2 rounded-md">Sort by Time</button>
                <button class="bg-blue-600 text-white px-4 py-2 rounded-md ml-4">Sort by Airport</button>
            </div>
            <div id="flight-list" class="flex-grow">
                <!-- Flight list will be dynamically generated here -->
            </div>
        </section>

        <!-- Add New Flight -->
        <section id="add-flight" class="bg-nav p-6 rounded-lg shadow-lg h-full flex flex-col">
            <h2 class="text-2xl font-bold mb-4">Add New Flight</h2>
            <form action="#" method="POST" class="flex flex-col flex-grow">
                <div class="mb-4 flex-grow">
                    <label for="new-flight-number" class="block text-sm font-medium text-gray-700">Flight Number</label>
                    <input type="text" id="new-flight-number" name="new-flight-number" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                </div>
                <!-- Other flight details inputs here -->
                <div class="flex justify-end">
                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md">Add Flight</button>
                </div>
            </form>
        </section>

        <!-- Add New Passenger -->
        <section id="add-passenger" class="bg-nav p-6 rounded-lg shadow-lg h-full flex flex-col">
            <h2 class="text-2xl font-bold mb-4">Add New Passenger</h2>
            <form action="#" method="POST" class="flex flex-col flex-grow">
                <div class="mb-4 flex-grow">
                    <label for="passenger-name" class="block text-sm font-medium text-gray-700">Passenger Name</label>
                    <input type="text" id="passenger-name" name="passenger-name" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                </div>
                <!-- Other passenger details inputs here -->
                <div class="flex justify-end">
                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md">Add Passenger</button>
                </div>
            </form>
        </section>

        <!-- Passenger Overview -->
        <section id="passengers" class="bg-nav p-6 rounded-lg shadow-lg h-full flex flex-col">
            <h2 class="text-2xl font-bold mb-4">Passenger Overview</h2>
            <form action="#" method="POST" class="flex flex-col flex-grow">
                <div class="mb-4 flex-grow">
                    <label for="flight-number-passenger" class="block text-sm font-medium text-gray-700">Flight Number</label>
                    <input type="text" id="flight-number-passenger" name="flight-number-passenger" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                </div>
                <div class="flex justify-end">
                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md">Retrieve Passenger List</button>
                </div>
            </form>
            <div id="passenger-list" class="mt-4 flex-grow">
                <!-- Passenger list will be dynamically generated here -->
            </div>
        </section>

        <!-- Edit Passenger Details -->
        <section id="edit-passenger" class="bg-nav p-6 rounded-lg shadow-lg h-full flex flex-col">
            <h2 class="text-2xl font-bold mb-4">Edit Passenger Details</h2>
            <form action="#" method="POST" class="flex flex-col flex-grow">
                <div class="mb-4 flex-grow">
                    <label for="search-passenger" class="block text-sm font-medium text-gray-700">Search Passenger</label>
                    <input type="text" id="search-passenger" name="search-passenger" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                </div>
                <!-- Passenger detail edit inputs here -->
                <div class="flex justify-end">
                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md">Update Passenger</button>
                </div>
            </form>
        </section>
    </div>
</div>

<!-- Container om footer aan de vloer te plakken -->
<div class="min-h-screen"></div>

<!-- Footer Include -->
<?php include 'includes/footer.php'; ?>
</body>
</html>
