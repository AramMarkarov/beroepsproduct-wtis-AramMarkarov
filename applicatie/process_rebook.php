<?php
session_start();
require_once 'db_connectie.php';
require_once 'includes/data_functies.php';
// Medewerker check
if (!isset($_SESSION['employee'])) {
    header('Location: employeelogin.php');
    exit();
}

// Check if session values are set
if (!isset($_SESSION['passagiernummer'], $_SESSION['originalFlight'])) {
    die("Session values not set.");
}

// Database connection
$db = maakVerbinding();

if ($db) {
    // Fetch alle toekomstige vluchten die niet op capaciteit zijn in een dropdown
    $stmt = $db->prepare("SELECT vluchtnummer, bestemming, vertrektijd FROM Vlucht WHERE vertrektijd > GETDATE()");
    $stmt->execute();
    $flights = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        // POST waarden van nieuwe vlucht en nieuwe zitplaats
        $newFlight = $_POST['newFlight'] ?? null;
        $newSeat = $_POST['newSeat'] ?? null;

        // Validatie of een nieuwe vlucht en nieuwe zitplaats is gegeven
        if ($newFlight && $newSeat) {
            // Check of de zitplaats beschikbaar is in die vlucht
            $stmt = $db->prepare("SELECT COUNT(*) AS count FROM Passagier WHERE vluchtnummer = :vluchtnummer AND stoel = :stoel");
            $stmt->bindParam(':vluchtnummer', $newFlight);
            $stmt->bindParam(':stoel', $newSeat);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result['count'] > 0) {
                $error = "Seat $newSeat is already assigned to another passenger on the new flight.";
            } else {
                // Fetch balienummer van IncheckenVlucht gebaseerd op vluchtnummer
                $stmt = $db->prepare("SELECT balienummer FROM IncheckenVlucht WHERE vluchtnummer = :vluchtnummer");
                $stmt->bindParam(':vluchtnummer', $newFlight);
                $stmt->execute();
                $result = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$result) {
                    $error = "No baggage counter found for the selected flight.";
                } else {
                    $balienummer = $result['balienummer'];

                    // Update passagier details met een nieuwe vlucht, zitplaats en balienummer
                    $stmt = $db->prepare("UPDATE Passagier SET vluchtnummer = :newFlight, stoel = :newSeat, balienummer = :balienummer WHERE passagiernummer = :passagiernummer AND vluchtnummer = :originalFlight");
                    $stmt->bindParam(':newFlight', $newFlight);
                    $stmt->bindParam(':newSeat', $newSeat);
                    $stmt->bindParam(':balienummer', $balienummer);
                    $stmt->bindParam(':passagiernummer', $_SESSION['passagiernummer']);
                    $stmt->bindParam(':originalFlight', $_SESSION['originalFlight']);

                    if ($stmt->execute()) {
                        $success = "Passenger rebooked successfully! New flight: $newFlight, Seat: $newSeat, Baggage counter: $balienummer";
                        // Verwijder waarden uit sessie zodra de query UPDATE is afgelopen
                        unset($_SESSION['passagiernummer']);
                        unset($_SESSION['originalFlight']);
                    } else {
                        $error = "Error updating passenger details: " . $stmt->errorInfo()[2];
                    }
                }
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
    <title>Process Rebook</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <?php include 'includes/tailwind_script.php'; ?>
</head>
<body class="bg-background text-text text-body">

<!-- Navigation Bar Include -->
<?php include 'includes/nav.php'; ?>

<!-- Main Content -->
<div class="mx-[10%] w-[80%] py-8">
    <div class="bg-nav p-6 rounded-lg shadow-lg">
        <h2 class="text-2xl font-bold mb-4">Process Rebook</h2>
        <?php if (!empty($error)): ?>
            <p class="text-red-500 mb-4"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <p class="text-green-500 mb-4"><?php echo htmlspecialchars($success); ?></p>
            <div class="flex justify-end mt-4">
                <a href="rebook_passenger.php" class="w-full py-2 px-4 text-white bg-button hover:bg-hover rounded-md transition duration-150 ease-in-out">Back to Rebook Passenger</a>
            </div>
        <?php else: ?>
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" class="space-y-4">
                <input type="hidden" name="passagiernummer" value="<?php echo isset($_SESSION['passagiernummer']) ? htmlspecialchars($_SESSION['passagiernummer']) : ''; ?>">
                <input type="hidden" name="originalFlight" value="<?php echo isset($_SESSION['originalFlight']) ? htmlspecialchars($_SESSION['originalFlight']) : ''; ?>">
                <div>
                    <label for="newFlight" class="block text-sm font-medium text-gray-700">New Flight Number</label>
                    <select id="newFlight" name="newFlight" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                        <option value="">Select Flight Number</option>
                        <?php foreach ($flights as $flight): ?>
                            <option value="<?php echo htmlspecialchars($flight['vluchtnummer']); ?>"><?php echo htmlspecialchars($flight['vluchtnummer'] . ' - ' . $flight['bestemming'] . ' (' . date('Y-m-d H:i', strtotime($flight['vertrektijd'])) . ')'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="newSeat" class="block text-sm font-medium text-gray-700">New Seat (Format: A01)</label>
                    <input type="text" id="newSeat" name="newSeat" placeholder="Example: K35" pattern="[A-Z]\d{2}" title="Please enter a seat in the format A01" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" value="<?php echo isset($_POST['newSeat']) ? htmlspecialchars($_POST['newSeat']) : ''; ?>">
                </div>
                <div class="flex justify-end">
                    <button type="submit" class="w-full py-2 px-4 text-white hover:text-text bg-button hover:bg-hover rounded-md transition duration-150 ease-in-out">Confirm Rebooking</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="min-h-screen"></div>
<!-- Footer Include -->
<?php include 'includes/footer.php'; ?>

</body>
</html>
