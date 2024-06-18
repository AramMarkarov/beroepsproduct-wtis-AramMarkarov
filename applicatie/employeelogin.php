<?php
session_start();
require_once 'db_connectie.php';

// Variabelen
$error = '';

// Check of een form is gepost
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $balienummer = $_POST['balienummer'];
    $password = $_POST['wachtwoord'];

    try {
        $db = maakVerbinding();
        // Prepare en execute query
        $stmt = $db->prepare("SELECT * FROM Balie WHERE balienummer = :balienummer");
        $stmt->bindParam(':balienummer', $balienummer);

        if (!$stmt->execute()) {
            die("Failed to execute query: " . implode(", ", $stmt->errorInfo()));
        }

        // Fetch medewerker data
        $employee = $stmt->fetch(PDO::FETCH_ASSOC);

        // Check wachtwoord
        if ($employee) {
            if (password_verify($password, $employee['wachtwoord'])) {
                // Successful login
                $_SESSION['employee'] = $employee['balienummer'];
                header('Location: employee_dashboard.php'); // Redirect to passenger dashboard or desired page
                exit();
            } else {
                // Invalid login
                $error = "Invalid password, try again.";
            }
        }
    } catch
        (PDOException $e) {
            // Database error
            $error = "An error has occured, try again later.";
        }
}

// Fetch beschikbare balienummers voor dropdown menu
try {
    $db = maakVerbinding();
    $stmt = $db->prepare("SELECT DISTINCT balienummer FROM Balie");
    $stmt->execute();
    $balienummers = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    // Database error
    $error = "balienummers could not be fetched, try again later.";
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
        <p class="mt-6 text-center text-title font-extrabold">GelreAir employee login</p>
      </div>
      <form class="mt-8 space-y-6" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
          <label for="balienummer"></label>
          <select id="balienummer" name="balienummer" required class="w-full px-3 py-2 rounded-md">
            <option value="" disabled selected>Choose balienummer</option>
            <?php foreach ($balienummers as $balienummer): ?>
                <option value="<?php echo htmlspecialchars($balienummer); ?>"><?php echo htmlspecialchars($balienummer); ?></option>
            <?php endforeach; ?>
        </select>
          <label for="wachtwoord"></label>
          <input id="wachtwoord" name="wachtwoord" type="password" autocomplete="current-wachtwoord"
                                                 required class="w-full px-3 py-2 rounded-md" placeholder="Password">
        <button type="submit" class="group relative w-full flex justify-center py-2 px-4 font-medium text-white hover:text-text bg-button hover:bg-hover rounded-md transition duration-150 ease-in-out">
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
