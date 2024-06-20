<?php
session_start();
require_once 'db_connectie.php';

// Medewerker check
if (!isset($_SESSION['employee'])) {
    header('Location: employeelogin.php');
    exit();
}

// Fetch balienummer
$employeeCounter = $_SESSION['employee'];

// Variabelen
$error = '';
$flightDetails = [];
$warning = '';
$datetime = null;
$currentDateTime = null;
$formattedTime = '';

// Check of een GET aanvraag en een vluchtnummer is geplaatst
if ($_SERVER["REQUEST_METHOD"] === "GET" && isset($_GET['flight-number'])) {
    $flightNumber = $_GET['flight-number'];

    // Valideer vluchtnummer
    if (empty($flightNumber)) {
        $error = "Please enter a flight number.";
    } else {
        // Als een vluchtnummer is ingevuld
        $db = maakVerbinding();

        if ($db) {
            $stmt = $db->prepare(
                "SELECT v.vluchtnummer, v.bestemming, v.vertrektijd, iv.balienummer, v.gatecode, l.naam AS luchthaven_naam
                 FROM vlucht v
                 JOIN luchthaven l ON v.bestemming = l.luchthavencode
                 JOIN IncheckenVlucht iv ON v.vluchtnummer = iv.vluchtnummer
                 WHERE v.vluchtnummer = :flightNumber"
            );
            $stmt->bindParam(':flightNumber', $flightNumber);

            try {
                if ($stmt->execute()) {
                    $flightDetails = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    // Controle of vluchtnummer bestaat
                    if (empty($flightDetails)) {
                        $error = "No flights found for this flight number.";
                    } else {
                        try { // Zet tijd in juiste format
                            $datetime = new DateTime($flightDetails[0]['vertrektijd']);
                            $formattedTime = $datetime->format('d/m/Y H:i');
                            $currentDateTime = new DateTime();
                        } catch (Exception $e) {
                            $error = "Error processing departure time: " . $e->getMessage();
                        }
                        // Check of vlucht al vertrokken is
                        if ($datetime && $currentDateTime) {
                            if ($datetime < $currentDateTime) {
                                $warning = "This flight has already departed.";
                            }
                            // Check of passagier bij de juiste balie is voor bagage inchecken
                            if ($flightDetails[0]['balienummer'] !== $employeeCounter) {
                                $warning .= " Passenger needs to go to counter " . htmlspecialchars($flightDetails[0]['balienummer']) . " for check-in.";
                            }
                        }
                    }
                } else { // Error handling
                    $errorInfo = $stmt->errorInfo();
                    $error = "SQL error: " . $errorInfo[2];
                }
            } catch (PDOException $e) {
                $error = "Error fetching flight: " . $e->getMessage();
            }
        } else {
            $error = "Database connection failed.";
        }
    } // Gereed voor een nieuwe vluchtnummer
} elseif ($_SERVER["REQUEST_METHOD"] === "GET" && !isset($_GET['flight-number'])) {
    $error = "Please enter a flight number.";
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

<!-- Main Content -->
<div class="mx-[10%] w-[80%] py-8">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
        <!-- Flight Information -->
        <section id="flights" class="bg-nav p-6 rounded-lg shadow-lg h-full flex flex-col">
            <h2 class="text-2xl font-bold mb-4">Flight Information</h2>
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="GET" class="flex flex-col flex-grow">
                <div class="mb-4 flex-grow">
                    <label for="flight-number" class="block text-sm font-medium text-gray-700">Flight Number</label>
                    <input type="text" id="flight-number" name="flight-number" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                </div>
                <div class="flex justify-end">
                    <button type="submit" class="w-full py-2 px-4 text-white hover:text-text bg-button hover:bg-hover rounded-md transition duration-150 ease-in-out">Retrieve Flight Info</button>
                </div>
            </form>
            <?php if (!empty($error)): ?>
                <p class="text-red-500 mt-4"><?php echo htmlspecialchars($error); ?></p>
            <?php endif; ?>
            <?php if (!empty($warning)): ?>
                <p class="text-red-500 mt-4"><?php echo htmlspecialchars($warning); ?></p>
            <?php endif; ?>
            <?php if (!empty($flightDetails)): ?>
                <table class="table-auto w-full max-w-lg mt-8">
                    <thead>
                    <tr>
                        <th class="px-4 py-2">Flight Number</th>
                        <th class="px-4 py-2">Destination</th>
                        <th class="px-4 py-2">Departure Time</th>
                        <th class="px-4 py-2">Counter Number</th>
                        <th class="px-4 py-2">Gate Code</th>
                        <th class="px-4 py-2">Actions</th>
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
                            <td class="border px-4 py-2">
                                <?php
                                // Additional condition to display check-in button
                                if ($datetime && $currentDateTime && $datetime >= $currentDateTime && $flight['balienummer'] === $employeeCounter) {
                                    echo '<a href="employee_bag_checkin.php?vluchtnummer=' . htmlspecialchars($flight['vluchtnummer']) . '" class="text-blue-600 hover:text-blue-900">Check-in Bags</a>';
                                } else {
                                    echo 'Needs to go to correct counter';
                                }
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <!-- View All Flights Button -->
            <div class="mt-4 flex justify-center">
                <button onclick="location.href='all_flights.php'" class="w-full py-2 px-4 text-white hover:text-text bg-button hover:bg-hover rounded-md transition duration-150 ease-in-out">
                    View All Flights
                </button>
            </div>
        </section>

        <!-- Add Flight and Add Passenger Buttons -->
        <section id="actions" class="bg-nav p-6 rounded-lg shadow-lg h-full flex flex-col">
            <h2 class="text-2xl font-bold mb-4">Actions</h2>
            <div class="flex flex-col gap-4">
                <button onclick="location.href='add_flight.php'" class="w-full py-2 px-4 text-white hover:text-text bg-button hover:bg-hover rounded-md transition duration-150 ease-in-out">
                    Add new flight
                </button>
                <button onclick="location.href='add_passenger.php'" class="w-full py-2 px-4 text-white hover:text-text bg-button hover:bg-hover rounded-md transition duration-150 ease-in-out">
                    Add new passenger
                </button>
                <button onclick="location.href='rebook_passenger.php'" class="w-full py-2 px-4 text-white hover:text-text bg-button hover:bg-hover rounded-md transition duration-150 ease-in-out">
                    Rebook passenger
                </button>
                <button onclick="location.href='overview.php'" class="w-full py-2 px-4 text-white hover:text-text bg-button hover:bg-hover rounded-md transition duration-150 ease-in-out">
                    Overview all passengers and flightoverview
                </button>
            </div>
        </section>

        <!-- Other sections here -->
    </div>
</div>

<div class="min-h-screen"></div>
<!-- Footer Include -->
<?php include 'includes/footer.php'; ?>

</body>
</html>
