<?php
session_start();
require_once 'db_connectie.php';

// Medewerker check
if (!isset($_SESSION['employee'])) {
    header('Location: employeelogin.php');
    exit();
}

$error = '';
$success = '';

$db = maakVerbinding();

// Wanneer een POST aanvraag is, loopt deze code
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Passagiersnaam wordt in een variabele gestopt
    if (isset($_POST['passengerName'])) {
        $passengerName = $_POST['passengerName'];

        // Fetch passasier details
        $stmt = $db->prepare("SELECT * FROM Passagier WHERE naam = :naam");
        $stmt->bindParam(':naam', $passengerName);
        $stmt->execute();
        $passenger = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($passenger) {
            // Bewaar passagiernummer en vluchtnummer in de sessie, deze zijn later nodig om te identificeren welke regel in de DB een UPDATE nodig heeft
            $_SESSION['passagiernummer'] = $passenger['passagiernummer'];
            $_SESSION['originalFlight'] = $passenger['vluchtnummer'];

            // Fetch komende vluchten voor de passagier (subquery) binnen 7 dagen
            $stmtFlights = $db->prepare("SELECT V.vluchtnummer, L.naam AS bestemming, V.vertrektijd
                                         FROM Vlucht V
                                         JOIN Luchthaven L ON V.bestemming = L.luchthavencode
                                         WHERE V.vluchtnummer IN (
                                             SELECT vluchtnummer FROM Passagier WHERE naam = :naam
                                         ) AND V.vertrektijd > GETDATE() AND V.vertrektijd <= DATEADD(day, 7, GETDATE())");
            $stmtFlights->bindParam(':naam', $passengerName);
            $stmtFlights->execute();
            $flights = $stmtFlights->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $error = "Passenger not found with name: $passengerName";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rebook Passenger</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <?php include 'includes/tailwind_script.php'; ?>
</head>
<body class="bg-background text-text text-body">

<!-- Navigation Bar Include -->
<?php include 'includes/nav.php'; ?>

<!-- Main Content -->
<div class="mx-[10%] w-[80%] py-8">
    <div class="bg-nav p-6 rounded-lg shadow-lg">
        <h2 class="text-2xl font-bold mb-4">Rebook Passenger</h2>
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" class="space-y-4">
            <div>
                <label for="passengerName" class="block text-sm font-medium text-gray-700">Passenger Name</label>
                <input type="text" id="passengerName" name="passengerName" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
            </div>
            <div class="flex justify-end">
                <button type="submit" class="w-full py-2 px-4 text-white hover:text-text bg-button hover:bg-hover rounded-md transition duration-150 ease-in-out">Search Passenger</button>
            </div>
        </form>

        <?php if (isset($passenger['naam'])): ?>
            <hr class="my-4">

            <h3 class="text-lg font-bold mb-2">Passenger Details</h3>
            <p><strong>Name:</strong> <?php echo htmlspecialchars($passenger['naam']); ?></p>
            <p><strong>Flight(s) to Rebook:</strong></p>
            <?php if (isset($flights) && count($flights) > 0): ?>
                <form action="process_rebook.php" method="POST">
                    <input type="hidden" name="passagiernummer" value="<?php echo htmlspecialchars($passenger['passagiernummer']); ?>">
                    <input type="hidden" name="originalFlight" value="<?php echo htmlspecialchars($passenger['vluchtnummer']); ?>">
                    <div class="space-y-2">
                        <?php foreach ($flights as $flight): ?>
                            <label class="block">
                                <input type="radio" name="newFlight" value="<?php echo htmlspecialchars($flight['vluchtnummer']); ?>" required>
                                <span class="ml-2"><?php echo htmlspecialchars($flight['bestemming']); ?> - Departure: <?php echo isset($flight['vertrektijd']) ? htmlspecialchars($flight['vertrektijd']) : 'N/A'; ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <div class="flex justify-end mt-4">
                        <button type="submit" class="w-full py-2 px-4 text-white hover:text-text bg-button hover:bg-hover rounded-md transition duration-150 ease-in-out">Rebook Passenger</button>
                    </div>
                </form>
            <?php else: ?>
                <p>No upcoming flights found for this passenger within 7 days.</p>
            <?php endif; ?>
        <?php elseif ($error): ?>
            <p class="text-red-500 mt-4"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>
    </div>
</div>

<div class="min-h-screen"></div>
<!-- Footer Include -->
<?php include 'includes/footer.php'; ?>

</body>
</html>
