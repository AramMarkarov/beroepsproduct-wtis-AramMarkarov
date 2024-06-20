<?php
session_start();
require_once 'db_connectie.php';

// Medewerker check
if (!isset($_SESSION['employee'])) {
    header('Location: employeelogin.php');
    exit();
}

// Variabelen
$error = '';
$success = '';

$db = maakVerbinding();
// Hier is het veilig meteen de DB te raadplegen omdat een baliemedewerker moet ingelogd zijn
if ($db) {
    // Fetch alle vluchten in de toekomst die niet op passagier capaciteit zit voor de dropdown
    $stmt = $db->prepare("SELECT v.vluchtnummer, v.bestemming, v.vertrektijd, v.max_aantal 
                          FROM Vlucht v 
                          LEFT JOIN (
                              SELECT vluchtnummer, COUNT(*) AS current_passengers 
                              FROM Passagier 
                              GROUP BY vluchtnummer
                          ) p ON v.vluchtnummer = p.vluchtnummer
                          WHERE v.vertrektijd > GETDATE() 
                          AND (p.current_passengers IS NULL OR p.current_passengers < v.max_aantal)");
    $stmt->execute();
    $flights = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        // Ophalen POST data en default wachtwoord met het hash proces
        $passengerName = $_POST['passenger-name'];
        $flightNumber = $_POST['flight-number'];
        $geslacht = $_POST['geslacht'];
        $stoel = $_POST['stoel'];
        $wachtwoord = 'unsafe-pass';
        $hashedPassword = password_hash($wachtwoord, PASSWORD_DEFAULT);

        // Check of de geselecteerde vlucht nog steeds onder het maximaal toegestane is, als er achter elkaar passagiers toegevoegd worden, kan het zijn dat de vorige check niet genoeg is
        $validFlight = false;
        foreach ($flights as $flight) {
            if ($flight['vluchtnummer'] == $flightNumber) {
                $validFlight = true;
                break;
            }
        }

        if (!$validFlight) {
            $error = "Invalid flight number selected. Please choose a valid flight.";
        } else {
            // Check of de stoel al bezet is op de gegeven vlucht
            $stmt = $db->prepare("SELECT COUNT(*) AS count FROM Passagier WHERE vluchtnummer = :vluchtnummer AND stoel = :stoel");
            $stmt->bindParam(':vluchtnummer', $flightNumber);
            $stmt->bindParam(':stoel', $stoel);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result['count'] > 0) {
                $error = "Seat $stoel is already assigned to another passenger on this flight.";
            } else {
                // Fetch balienummer van tabel incheckenvlucht op basis van vluchtnummer, deze kan dan worden doorgegeven aan de passagier waar deze de bagage kan inchecken
                $stmt = $db->prepare("SELECT balienummer FROM IncheckenVlucht WHERE vluchtnummer = :vluchtnummer");
                $stmt->bindParam(':vluchtnummer', $flightNumber);
                $stmt->execute();
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $balienummer = $result['balienummer'];

                // Fetch het hoogste passagiernummer en doe +1 voor een uniek nummer
                $stmt = $db->query("SELECT MAX(passagiernummer) AS max_passagiernummer FROM Passagier");
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $nextPassagierNummer = $row['max_passagiernummer'] + 1;

                // Insert nieuwe passagier in de tabel Passagier
                $stmt = $db->prepare("INSERT INTO Passagier (passagiernummer, naam, vluchtnummer, geslacht, balienummer, stoel, inchecktijdstip, wachtwoord) 
                                      VALUES (:passagiernummer, :naam, :vluchtnummer, :geslacht, :balienummer, :stoel, GETDATE(), :wachtwoord)");
                $stmt->bindParam(':passagiernummer', $nextPassagierNummer);
                $stmt->bindParam(':naam', $passengerName);
                $stmt->bindParam(':vluchtnummer', $flightNumber);
                $stmt->bindParam(':geslacht', $geslacht);
                $stmt->bindParam(':balienummer', $balienummer);
                $stmt->bindParam(':stoel', $stoel);
                $stmt->bindParam(':wachtwoord', $hashedPassword);

                if ($stmt->execute()) {
                    $success = "Passenger added successfully! Please proceed to Counter " . htmlspecialchars($balienummer);
                    $_POST = array();
                } else {
                    $error = "Error adding passenger: " . $stmt->errorInfo()[2];
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
    <title>Add Passenger</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <?php include 'includes/tailwind_script.php'; ?>
</head>
<body class="bg-background text-text text-body">

<!-- Navigation Bar Include -->
<?php include 'includes/nav.php'; ?>

<!-- Main Content -->
<div class="mx-[10%] w-[80%] py-8">
    <div class="bg-nav p-6 rounded-lg shadow-lg">
        <h2 class="text-2xl font-bold mb-4">Add Passenger</h2>
        <?php if (!empty($error)): ?>
            <p class="text-red-500 mb-4"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <p class="text-green-500 mb-4"><?php echo htmlspecialchars($success); ?></p>
        <?php endif; ?>
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" class="space-y-4" id="add-passenger-form">
            <div>
                <label for="passenger-name" class="block text-sm font-medium text-gray-700">Passenger Name</label>
                <input type="text" id="passenger-name" name="passenger-name" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" value="<?php echo isset($_POST['passenger-name']) ? htmlspecialchars($_POST['passenger-name']) : ''; ?>">
            </div>
            <div>
                <label for="flight-number" class="block text-sm font-medium text-gray-700">Flight Number</label>
                <select id="flight-number" name="flight-number" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                    <option value="">Select Flight Number</option>
                    <?php foreach ($flights as $flight): ?>
                        <option value="<?php echo htmlspecialchars($flight['vluchtnummer']); ?>"><?php echo htmlspecialchars($flight['vluchtnummer'] . ' - ' . $flight['bestemming'] . ' (' . date('Y-m-d H:i', strtotime($flight['vertrektijd'])) . ')'); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="geslacht" class="block text-sm font-medium text-gray-700">Gender</label>
                <select id="geslacht" name="geslacht" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                    <option value="">Select Gender</option>
                    <option value="M">Male</option>
                    <option value="V">Female</option>
                    <option value="X">Other</option>
                </select>
            </div>
            <div>
                <label for="stoel" class="block text-sm font-medium text-gray-700">Seat (Format: A01)</label>
                <input type="text" id="stoel" name="stoel" placeholder="Example: K35" pattern="[A-Z]\d{2}" title="Please enter a seat in the format A01" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" value="<?php echo isset($_POST['stoel']) ? htmlspecialchars($_POST['stoel']) : ''; ?>">
            </div>
            <div class="flex justify-end">
                <button type="submit" class="w-full py-2 px-4 text-white hover:text-text bg-button hover:bg-hover rounded-md transition duration-150 ease-in-out">Add Passenger</button>
            </div>
        </form>
    </div>
</div>

<div class="min-h-screen"></div>
<!-- Footer Include -->
<?php include 'includes/footer.php'; ?>

</body>
</html>
