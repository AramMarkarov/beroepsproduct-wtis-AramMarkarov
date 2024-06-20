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

if ($db) {
    // Genereer nieuwe vluchtnummer, iedere vluchtnummer krijgt de nieuwste vluchtnummer + 1
    $stmt = $db->prepare("SELECT MAX(vluchtnummer) AS max_vluchtnummer FROM Vlucht");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $newFlightNumber = $result ? $result['max_vluchtnummer'] + 1 : 1;

    // Fetch bestemmingen
    $stmt = $db->prepare("SELECT luchthavencode, naam FROM Luchthaven");
    $stmt->execute();
    $destinations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch gates
    $stmt = $db->prepare("SELECT gatecode FROM Gate");
    $stmt->execute();
    $gates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch maatschappij en de code
    $stmt = $db->prepare("SELECT maatschappijcode, naam FROM Maatschappij");
    $stmt->execute();
    $airlines = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        // Ophalen POST data
        $destination = $_POST['destination'];
        $gatecode = $_POST['gatecode'];
        $maxAantal = $_POST['maxAantal'];
        $maxGewichtPP = $_POST['maxGewichtPP'];
        $vertrekTijd = $_POST['vertrekTijd'];
        $maatschappijcode = $_POST['maatschappijcode'];

        // Valideren of de ingevulde waarden boven de limieten komt
        if ($maxAantal < 1 || $maxAantal > 300) {
            $error = "Maximum number of passengers must be between 1 and 300.";
        } elseif ($maxGewichtPP <= 0 || $maxGewichtPP > 150.00) {
            $error = "Maximum weight per passenger must be a positive value and not exceed 150.00 kg.";
        } else {
            // Calculeer max gewicht
            $maxTotaalGewicht = $maxAantal * $maxGewichtPP;

            // Valideer max gewicht, mag niet boven numeric(6,2) komen
            if ($maxTotaalGewicht > 9999.99) {
                $error = "Maximum total weight exceeds the allowed limit, maxumum is 9999.99kg.";
            } else {
                // Valideer vertrekdatum, mag niet in het verleden zijn
                $currentDateTime = new DateTime();
                try {
                    $departureDateTime = new DateTime($vertrekTijd);

                    if ($departureDateTime <= $currentDateTime) {
                        $error = "The departure time cannot be in the past.";
                    } else {
                        // Format vertrektijd voor SQL Server wanneer vertrektijd in de toekomst is
                        $formattedVertrekTijd = $departureDateTime->format('Y-m-d H:i:s');

                        // Insert nieuwe gegevens in de DB tabel Vlucht
                        $stmt = $db->prepare("INSERT INTO Vlucht (vluchtnummer, bestemming, gatecode, max_aantal, max_gewicht_pp, max_totaalgewicht, vertrektijd, maatschappijcode) 
                                              VALUES (:vluchtnummer, :bestemming, :gatecode, :max_aantal, :max_gewicht_pp, :max_totaalgewicht, :vertrektijd, :maatschappijcode)");
                        $stmt->bindParam(':vluchtnummer', $newFlightNumber);
                        $stmt->bindParam(':bestemming', $destination);
                        $stmt->bindParam(':gatecode', $gatecode);
                        $stmt->bindParam(':max_aantal', $maxAantal);
                        $stmt->bindParam(':max_gewicht_pp', $maxGewichtPP);
                        $stmt->bindParam(':max_totaalgewicht', $maxTotaalGewicht);
                        $stmt->bindParam(':vertrektijd', $formattedVertrekTijd);
                        $stmt->bindParam(':maatschappijcode', $maatschappijcode);

                        if ($stmt->execute()) {
                            $success = "Flight added successfully!";

                            // Toevoegen aan IncheckenVlucht tabel
                            // Zoek balienummer op basis van maatschappijcode in IncheckenMaatschappij tabel
                            $stmt = $db->prepare("SELECT balienummer FROM IncheckenMaatschappij WHERE maatschappijcode = :maatschappijcode");
                            $stmt->bindParam(':maatschappijcode', $maatschappijcode);
                            $stmt->execute();
                            $row = $stmt->fetch(PDO::FETCH_ASSOC);

                            if ($row) {
                                $balienummer = $row['balienummer'];

                                // Voeg toe aan IncheckenVlucht tabel
                                $stmt = $db->prepare("INSERT INTO IncheckenVlucht (vluchtnummer, balienummer) 
                                                      VALUES (:vluchtnummer, :balienummer)");
                                $stmt->bindParam(':vluchtnummer', $newFlightNumber);
                                $stmt->bindParam(':balienummer', $balienummer);
                                $stmt->execute();
                            } else {
                                $error = "No matching balienummer found for maatschappijcode: $maatschappijcode in IncheckenMaatschappij table";
                            }

                            // Reset formwaarden voor volgende toevoeging
                            $newFlightNumber++;
                            $_POST = array();
                        } else {
                            $errorInfo = $stmt->errorInfo();
                            $error = "SQL error: " . $errorInfo[2];
                        }
                    }
                } catch (Exception $e) {
                    $error = "Error parsing departure time: " . $e->getMessage();
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
    <title>Add New Flight</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <?php include 'includes/tailwind_script.php'; ?>
</head>
<body class="bg-background text-text text-body">

<!-- Navigation Bar Include -->
<?php include 'includes/nav.php'; ?>

<!-- Main Content -->
<div class="mx-[10%] w-[80%] py-8">
    <div class="bg-nav p-6 rounded-lg shadow-lg">
        <h2 class="text-2xl font-bold mb-4">Add New Flight</h2>
        <?php if (!empty($error)): ?>
            <p class="text-red-500 mb-4"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <p class="text-green-500 mb-4"><?php echo htmlspecialchars($success); ?></p>
        <?php endif; ?>
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" class="space-y-4">
            <div>
                <label for="flight-number" class="block text-sm font-medium text-gray-700">Flight Number</label>
                <input type="text" id="flight-number" name="flight-number" value="<?php echo htmlspecialchars($newFlightNumber); ?>" readonly class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
            </div>
            <div>
                <label for="destination" class="block text-sm font-medium text-gray-700">Destination</label>
                <select id="destination" name="destination" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                    <?php foreach ($destinations as $destination): // Dropwown voor alle bestemmingen?>
                        <option value="<?php echo htmlspecialchars($destination['luchthavencode']); ?>"><?php echo htmlspecialchars($destination['naam']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="gatecode" class="block text-sm font-medium text-gray-700">Gate Code</label>
                <select id="gatecode" name="gatecode" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                    <?php foreach ($gates as $gate): // Dropdown voor de gates?>
                        <option value="<?php echo htmlspecialchars($gate['gatecode']); ?>"><?php echo htmlspecialchars($gate['gatecode']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="maxAantal" class="block text-sm font-medium text-gray-700">Maximum Number of Passengers</label>
                <input type="number" id="maxAantal" name="maxAantal" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
            </div>
            <div>
                <label for="maxGewichtPP" class="block text-sm font-medium text-gray-700">Maximum Weight Per Passenger (kg)</label>
                <input type="number" step="0.01" id="maxGewichtPP" name="maxGewichtPP" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
            </div>
            <div>
                <label for="maxTotaalGewicht" class="block text-sm font-medium text-gray-700">Maximum Total Weight (kg)</label>
                <input type="number" step="0.01" id="maxTotaalGewicht" name="maxTotaalGewicht" value="<?php echo isset($_POST['maxAantal']) && isset($_POST['maxGewichtPP']) ? htmlspecialchars($_POST['maxAantal'] * $_POST['maxGewichtPP']) : ''; ?>" readonly class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
            </div>
            <div><label for="vertrekTijd" class="block text-sm font-medium text-gray-700">Departure Time</label>
                <input type="datetime-local" id="vertrekTijd" name="vertrekTijd" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
            </div>
            <div>
                <label for="maatschappijcode" class="block text-sm font-medium text-gray-700">Airline Code</label>
                <select id="maatschappijcode" name="maatschappijcode" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                    <?php foreach ($airlines as $airline): // Dropdown voor de Airlines?>
                        <option value="<?php echo htmlspecialchars($airline['maatschappijcode']); ?>"><?php echo htmlspecialchars($airline['naam']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex justify-end">
                <button type="submit" class="w-full py-2 px-4 text-white hover:text-text bg-button hover:bg-hover rounded-md transition duration-150 ease-in-out">Add Flight</button>
            </div>
        </form>
    </div>
</div>

<div class="min-h-screen"></div>
<!-- Footer Include -->
<?php include 'includes/footer.php'; ?>

<script>
    // Stukje JavaScript, dit kon via php gedaan worden. Om het live te updaten voor de medewerker, is JS een betere oplossing om live te kunnen handelen als de waarden te hoog zijn.
    // Dit is door chatGPT gegenereert en uitgevoerd, hoewel ik beperkt ervaring heb met JS, heb ik het vaker gebruikt, deels in iProject en in dit opzich werkt het volledig.

    // De waarden maxAantal en maxGewicht per persoon zijn opgevangen
    document.getElementById('maxAantal').addEventListener('input', updateMaxTotaalGewicht);
    document.getElementById('maxGewichtPP').addEventListener('input', updateMaxTotaalGewicht);
    // Deze functie berekent de maximale totaal gewicht op een vlucht afhankelijk van de waarden die door eventlistener live zijn opgehaald
    function updateMaxTotaalGewicht() {
        const maxAantal = document.getElementById('maxAantal').value;
        const maxGewichtPP = document.getElementById('maxGewichtPP').value;
        // Berekening en resultaat is geplakt op de label maxTotaalGewicht.
        document.getElementById('maxTotaalGewicht').value = (maxAantal * maxGewichtPP).toFixed(2);
    }
</script>

</body>
</html>
