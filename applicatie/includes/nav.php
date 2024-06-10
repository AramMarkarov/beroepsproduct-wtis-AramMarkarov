<?php
// Storing page url for redirect after logging in
$lastUrlPart = basename($_SERVER['REQUEST_URI']);

echo '<nav class="mb-4 bg-nav rounded-md text-text text-body">
    <div class="mx-[20%] w-[60%] flex justify-between items-center z-0 py-6">
        <div class="flex space-x-12 flex-grow">
            <a href="homepage.php" class="hover:text-hover hover:underline transition duration-150 ease-in-out">Home</a>
            <a href="#" class="hover:text-hover hover:underline transition duration-150 ease-in-out">Flights</a>
            <a href="#" class="hover:text-hover hover:underline transition duration-150 ease-in-out">Services</a>
            <a href="#" class="hover:text-hover hover:underline transition duration-150 ease-in-out">Contact</a>
        </div>
        <div class="flex space-x-12">
            <a href="employeelogin.php" class="flex items-center px-4 py-2 text-text rounded-md hover:bg-hover transition duration-150 ease-in-out">
                <span>Employee</span>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 ml-2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/>
                </svg>
            </a>
            <a href="login.php?forward=' . urlencode($lastUrlPart) . '" class="flex items-center px-4 py-2 text-text rounded-md hover:bg-hover transition duration-150 ease-in-out">
                <span>Login</span>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 ml-2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/>
                </svg>
            </a>
        </div>
    </div>
</nav>';
