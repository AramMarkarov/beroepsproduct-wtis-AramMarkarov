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
        <p class="mt-6 text-center text-title font-extrabold">Inloggen bij GelreAir</p>
      </div>
        <form class="mt-8 space-y-6" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <input id="gebruikersnaam" name="gebruikersnaam" type="text" autocomplete="gebruikersnaam"
                   required class="w-full px-3 py-2 rounded-md" placeholder="Gebruikersnaam">
                    <input id="wachtwoord" name="wachtwoord" type="password" autocomplete="current-wachtwoord"
                   required class="w-full px-3 py-2 rounded-md" placeholder="Wachtwoord">
            <button type="submit" class="group relative w-full flex justify-center py-2 px-4 font-medium text-white hover:text-text bg-button hover:bg-hover rounded-md transition duration-150 ease-in-out">
                    Inloggen
                </button>
            </form>
    </div>
  </div>

  <!-- footer -->
  <?php include 'includes/footer.php'; ?>
</body>
</html>