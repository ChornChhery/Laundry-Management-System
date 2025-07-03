<?php
session_start();
require_once("connectDB.php"); // ใช้ PDO

if (!isset($pdo)) {
    die("Database connection not established.");
}

if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit();
}

$username = $_SESSION['username'];
$stmt = $pdo->prepare("
    SELECT 
        b.*,
        w.machine_images,
        w.machine_name,
        w.status as machine_status,
        m.capacity,
        m.price_per_use,
        m.washing_time,
        b.usage_status as status
    FROM bookings b
    JOIN washing_machines w ON b.machine_name = w.machine_name
    JOIN machine_types m ON w.serial_number = m.serial_number
    WHERE b.username = ?
    ORDER BY b.booking_date DESC
");
$stmt->execute([$username]);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user profile data
$username = $_SESSION['username'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
$stmt->execute([$username]);
$user = $stmt->fetch();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>History - Jamoon Laundry</title>
    <link rel="icon" href="logo.png">
    <link rel="stylesheet" href="style5.css">

</head>
<body>
    <header class="header">
        <div class="logo">
            <img src="logo.png" alt="Jamoon Laundry">
        </div>
        <nav class="nav-menu">
            <a href="dashboard.php">Home</a>
            <a href="about.php">About Us</a>
            <a href="about.php">Contact Us</a>
            <a href="history.php">History</a>
        </nav>
        <div class="profile-section">
            <div class="profile-image" onclick="window.location.href='profile.php'">
                <img src="<?php echo $user['profile_picture'] ? 'assets/images/profiles/' . $user['profile_picture'] : 'user.png'; ?>" alt="Profile">
            </div>
            <div class="profile-info">
                <span class="username"><?php echo $_SESSION['username']; ?></span>
                <a href="index.php?logout=true" class="logout-link">Logout</a>
            </div>
        </div>
    </header>

    <div class="history-container">
        <div class="filter-buttons">
            <button class="filter-button active" data-filter="all">All</button>
            <button class="filter-button" data-filter="completed">Completed</button>
            <button class="filter-button" data-filter="pending">Pending</button>
            <button class="filter-button" data-filter="cancelled">Cancelled</button>
        </div>

        <?php if (empty($bookings)): ?>
            <div class="no-bookings">
                <h2>No booking history found</h2>
                <p>You haven't made any bookings yet.</p>
            </div>
        <?php else: ?>
            <div class="booking-grid">
                <?php foreach ($bookings as $booking): ?>
                    <div class="booking-card" 
                        data-status="<?php echo $booking['usage_status']; ?>" 
                        data-machine-status="<?php echo $booking['machine_status']; ?>">
                        <img src="<?php echo $booking['machine_images']; ?>" alt="Machine" class="booking-image">
                        <div class="booking-details">
                            <div class="booking-reference">
                                Ref: <?php echo $booking['booking_reference']; ?>
                            </div>
                            <div class="booking-status status-<?php echo $booking['usage_status']; ?>">
                                <?php echo ucfirst($booking['usage_status']); ?>
                            </div>
                            <h3><?php echo $booking['machine_name']; ?></h3>
                            <div class="booking-info">
                                <div class="booking-date" data-booking-time="<?php echo $booking['booking_date']; ?>">
                                    Date: <?php echo date('F j, Y, g:i a', strtotime($booking['booking_date'])); ?>
                                </div>
                                <p>Capacity: <?php echo $booking['capacity']; ?> kg</p>
                                <p>Price: <?php echo $booking['price_per_use']; ?> บาท</p>
                                <p>Duration: <?php echo $booking['washing_time']; ?> minutes</p>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

<script>
        // Filter functionality
        document.querySelectorAll('.filter-button').forEach(button => {
            button.addEventListener('click', function() {
                // Remove active class from all buttons
                document.querySelectorAll('.filter-button').forEach(btn => {
                    btn.classList.remove('active');
                });
                // Add active class to clicked button
                this.classList.add('active');

                const filter = this.dataset.filter;
                const bookingCards = document.querySelectorAll('.booking-card');

                bookingCards.forEach(card => {
                    switch(filter) {
                        case 'all':
                            card.style.display = 'block';
                            break;
                            
                        case 'completed':
                            card.style.display = card.dataset.status === 'เสร็จสิ้น' ? 'block' : 'none';
                            break;
                            
                        case 'pending':
                            card.style.display = ['รอเริ่มทำงาน', 'กำลังทำงาน'].includes(card.dataset.status) ? 'block' : 'none';
                            break;
                            
                        case 'cancelled':
                            card.style.display = card.dataset.status === 'ไม่เสร็จ' ? 'block' : 'none';
                            break;
                    }
                });
            });
        });

        // Sort functionality
        function sortBookings(order) {
            const bookingGrid = document.querySelector('.booking-grid');
            const bookings = Array.from(bookingGrid.children);
            
            bookings.sort((a, b) => {
                const dateA = new Date(a.querySelector('.booking-info p').textContent);
                const dateB = new Date(b.querySelector('.booking-info p').textContent);
                return order === 'asc' ? dateA - dateB : dateB - dateA;
            });

            bookings.forEach(booking => bookingGrid.appendChild(booking));
        }
</script>
</body>
</html>