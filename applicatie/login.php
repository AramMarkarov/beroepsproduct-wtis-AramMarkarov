<?php
session_start();
require_once 'db_connectie.php';

// Variabelen
$error = '';

// Check of een form is verstuurd (login verzoek)
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = $_POST['gebruikersnaam'];
    $password = $_POST['wachtwoord'];

    try {
        $db = maakVerbinding();
        // Prepare query die de gegeven naam zoekt
        $stmt = $db->prepare("SELECT * FROM Passagier WHERE naam = :username");
        $stmt->bindParam(':username', $username);

        if (!$stmt->execute()) {
            die("Failed to execute query: " . implode(", ", $stmt->errorInfo()));
        }
        // Fetch passagier data om later met het wachtwoord te zoeken
        $passenger = $stmt->fetch(PDO::FETCH_ASSOC);

        // Verifieer of wachtwoord bestaat en bij de juiste gebruiker overeenkomen
        if ($passenger && password_verify($password, $passenger['wachtwoord'])) {
            // Succesvolle login
            $_SESSION['passenger'] = $passenger;
            header('Location: passenger_dashboard.php'); // Redirect to passenger dashboard or desired page
            exit();
        } else {
            // Incorrecte gegevens
            $error = "Invalid username or password. Please try again.";
        }
    } catch (PDOException $e) {
        // Database error
        $error = "An error has occurred. Please try again later.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login</title>
  <script src="https://cdn.tailwindcss.com"></script>

  <!-- Tailwind CSS script -->
  <?php include 'includes/tailwind_script.php'; ?>
</head>
<body class="bg-background min-h-screen flex flex-col">

  <!-- navigation -->
  <?php include 'includes/nav.php'; ?>

  <div class="flex flex-grow items-center justify-center text-text px-6 py-12">
    <div class="w-full max-w-md space-y-8">
      <div>
        <p class="mt-6 text-center text-title font-extrabold">GelreAir login</p>
      </div>
      <form class="mt-8 space-y-6" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
        <input id="gebruikersnaam" name="gebruikersnaam" type="text" autocomplete="gebruikersnaam"
               required class="w-full px-3 py-2 rounded-md" placeholder="Username">
        <input id="wachtwoord" name="wachtwoord" type="password" autocomplete="current-wachtwoord"
               required class="w-full px-3 py-2 rounded-md" placeholder="Password">
        <button type="submit" class="w-full flex justify-center py-2 px-4 text-white hover:text-text bg-button hover:bg-hover rounded-md transition duration-150 ease-in-out">
          Login
        </button>
      </form>
      <?php if (!empty($error)): ?>
        <p class="text-red-500 mt-4"><?php echo htmlspecialchars($error); ?></p>
      <?php endif; ?>
    </div>
  </div>

  <!-- footer -->
  <?php include 'includes/footer.php'; ?>
</body>
</html>
