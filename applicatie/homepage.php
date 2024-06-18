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

    $stmt = $db->prepare(
        "SELECT vsud.vluchtnummer, v.bestemming, v.vertrektijd, l.naam AS luchthaven_naam
         FROM vlucht v
         JOIN luchthaven l ON v.bestemming = l.luchthavencode
         WHERE v.vluchtnummer = :flightNumber AND v.vertrektijd > GETDATE()"
    );
    $stmt->bindParam(':flightNumber', $flightNumber);
    // Check voor flightDetails, wanneer gevuld, zal data weergegeven worden, anders een van de volgende foutmeldingen voor de gebruiker
    // Gebruik van try voor makkelijker error handling
    try {
        if ($stmt->execute()) {
            $flightDetails = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($flightDetails)) {
                $error = "No future flights found in this flight number.";
            }
        }
    } catch (PDOException $e) {
        $error = "An issue appeared during the search of the flight, try again later or check the flightnumber";
    }
}
// Zet datetime om in relevante data, dus geen seconden en milliseconden.
if (!empty($flightDetails)) {
    try {
        $datetime = new DateTime($flightDetails[0]['vertrektijd']);
        $formattedTime = $datetime->format('d/m/Y H:i');
    } catch (Exception $e) {
        // In geval van tijdsweergave, een errorhandling
        $error = "Er is een fout opgetreden bij het verwerken van de vertrektijd.";
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
    <title>GelreAirport</title>

    <!-- Tailwind CSS script -->
    <?php include 'includes/tailwind_script.php'; ?>
</head>
<body class="bg-background text-text text-body">
<!-- Nav -->
<?php include 'includes/nav.php'; ?>
<div class="mx-[20%] w-[60%] min-h-screen flex flex-col items-center justify-center">
    <div class="text-center">
        <h1 class="text-headline font-bold mb-4">Welcome to GelreAir</h1>
        <p class="text-subheader mb-6">Your gateway to the world. Experience comfort and convenience with GelreAir.</p>
    </div>

    <form class="mt-8 space-y-6 w-1/2" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="get">
        <input id="flightNumber" name="flightNumber" type="text" required placeholder="Flightnumber" class="w-full px-3 py-2 rounded-md">
        <button type="submit" class="w-full py-2 px-4 text-white hover:text-text bg-button hover:bg-hover rounded-md transition duration-150 ease-in-out">Search flight</button>
    </form>

    <?php if (!empty($error)): ?>
        <p class="text-red-500 mt-4"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>

    <?php if (!empty($flightDetails)): ?>
        <table class="table-auto w-full max-w-lg mt-8">
            <thead>
                <tr>
                    <th class="px-4 py-2">Vluchtnummer</th>
                    <th class="px-4 py-2">Bestemming</th>
                    <th class="px-4 py-2">Vertrektijd</th>
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
</div>
<!-- Footer -->
<?php include 'includes/footer.php'; ?>
</body>
</html>
