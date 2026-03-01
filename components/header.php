<!-- components/header.php -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- AdminLTE & FontAwesome -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Optional Tailwind -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">

    <script>
        // Function to fetch expired count from the API and update the badge
        function updateBadge() {
            fetch('http://127.0.0.1:8000/auth/worker.php?get_expired_count=true')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const expiredCount = data.expired_count;
                        const badge = document.querySelector('.navbar-badge');
                        badge.textContent = expiredCount; // Update the badge with the count
                        if (expiredCount > 0) {
                            badge.classList.remove('badge-warning');
                            badge.classList.add('badge-danger'); // Change badge to red if count > 0
                        } else {
                            badge.classList.remove('badge-danger');
                            badge.classList.add('badge-warning'); // Keep it yellow if count is 0
                        }
                    }
                })
                .catch(error => console.error('Error fetching expired count:', error));
        }

        // Call the function when the page loads
        window.onload = function() {
            updateBadge();
        };
    </script>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">

<!-- Navbar -->
<nav class="main-header navbar navbar-expand navbar-white navbar-light">
    <ul class="navbar-nav">
        <li class="nav-item">
            <a class="nav-link" data-widget="pushmenu" href="#"><i class="fas fa-bars"></i></a>
        </li>
        <li class="nav-item d-none d-sm-inline-block">
            <a href="/" class="nav-link">Home</a>
        </li>
    </ul>
    <ul class="navbar-nav ml-auto">
        <li class="nav-item dropdown">
            <a class="nav-link" href="users">
                <i class="far fa-bell"></i>
                <span class="badge badge-warning navbar-badge">0</span> <!-- This will be dynamically updated -->
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="#"><i class="fas fa-user-circle"></i></a>
        </li>
    </ul>
</nav>
