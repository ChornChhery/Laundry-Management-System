<?php
session_start();
require_once("connectDB.php");

if (!isset($pdo)) {
    die("Database connection not established.");
}

function logError($message, $context = []) {
    $logEntry = date('Y-m-d H:i:s') . " - " . $message . " - " . json_encode($context) . "\n";
    file_put_contents('logs/booking_errors.log', $logEntry, FILE_APPEND);
}


// Post handler section
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    try {
        $pdo->beginTransaction();
        
        switch ($_POST['action']) {
            case 'create_booking':
                $booking_reference = uniqid('BOOK_');
                $username = $_SESSION['username'];
                $machine_name = $_POST['machine_name'];
                
                // Verify machine availability
                $stmt = $pdo->prepare("
                    SELECT status FROM washing_machines 
                    WHERE machine_name = ? AND status = 'ว่าง'
                    FOR UPDATE
                ");
                $stmt->execute([$machine_name]);
                
                if (!$stmt->fetch()) {
                    throw new Exception('เครื่องซักผ้าไม่ว่าง กรุณาเลือกเครื่องอื่น');
                }

                // Create booking record
                $stmt = $pdo->prepare("
                    INSERT INTO bookings 
                    (booking_reference, username, machine_name, booking_date, usage_status) 
                    VALUES (?, ?, ?, NOW(), 'รอเริ่มทำงาน')
                ");
                $stmt->execute([$booking_reference, $username, $machine_name]);
                

                // Update machine status
                $stmt = $pdo->prepare("
                    UPDATE washing_machines 
                    SET status = 'รอเริ่มทำงาน' 
                    WHERE machine_name = ?
                ");
                $stmt->execute([$machine_name]);

                $pdo->commit();
                echo json_encode([
                    'success' => true,
                    'booking_reference' => $booking_reference,
                    'machine_name' => $machine_name,
                    'booking_date' => time() // Send booking timestamp for timer
                ]);
                break;
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
    exit;
}

// Security check
if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit();
}

$machine_name = $_GET['machine'] ?? null;

// Get machine details
if ($machine_name) {
    $stmt = $pdo->prepare("
        SELECT 
            w.machine_name,
            w.status,
            w.machine_images,
            m.capacity,
            m.price_per_use,
            m.washing_time,
            b.end_time,
            b.usage_status,
            b.booking_reference,
            b.username AS booking_username,
            b.start_time,
            b.booking_date,
            UNIX_TIMESTAMP(COALESCE(b.booking_date, NOW())) AS booking_timestamp,
            CASE 
                WHEN b.usage_status = 'กำลังทำงาน' THEN 
                    GREATEST(TIMESTAMPDIFF(MINUTE, NOW(), COALESCE(b.end_time, NOW())), 0)
                WHEN b.usage_status = 'รอเริ่มทำงาน' THEN 
                    GREATEST(TIMESTAMPDIFF(MINUTE, NOW(), DATE_ADD(COALESCE(b.booking_date, NOW()), INTERVAL 30 MINUTE)), 0)
                ELSE 0
            END AS remaining_minutes
        FROM washing_machines w
        JOIN machine_types m ON w.serial_number = m.serial_number
        LEFT JOIN bookings b ON w.machine_name = b.machine_name 
            AND b.usage_status IN ('รอเริ่มทำงาน', 'กำลังทำงาน')
        WHERE w.machine_name = ?
        ORDER BY b.booking_date DESC 
        LIMIT 1
    ");
    $stmt->execute([$machine_name]);
    $machine = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Auto-update expired bookings
// $stmt = $pdo->prepare("
//     UPDATE bookings b
//     JOIN washing_machines w ON b.machine_name = w.machine_name
//     SET b.usage_status = CASE
//             WHEN b.usage_status = 'รอเริ่มทำงาน' AND TIMESTAMPDIFF(MINUTE, b.booking_date, NOW()) > 30 
//             THEN 'ไม่เสร็จ'
//             WHEN b.usage_status = 'กำลังทำงาน' AND b.end_time < NOW() 
//             THEN 'เสร็จสิ้น'
//         END,
//         w.status = 'ว่าง'
//     WHERE b.usage_status IN ('รอเริ่มทำงาน', 'กำลังทำงาน')
//     AND (
//         (b.usage_status = 'รอเริ่มทำงาน' AND TIMESTAMPDIFF(MINUTE, b.booking_date, NOW()) > 30)
//         OR (b.usage_status = 'กำลังทำงาน' AND b.end_time < NOW())
//     )
// ");
// $stmt->execute();

// Get user profile
$stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
$stmt->execute([$_SESSION['username']]);
$user = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking - Jamoon Laundry</title>
    <link rel="icon" href="logo.png">
    <link rel="stylesheet" href="style4.css">
</head>
<body>
    <div id="overlay" class="overlay" style="display: none;"></div>

    <header class="header">
        <div class="logo">
            <img src="logo.png" alt="Jamoon Laundry">
        </div>
        <nav class="nav-menu">
            <a href="dashboard.php">Home</a>
            <a href="#about">About Us</a>
            <a href="#contact">Contact Us</a>
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
    
    <div class="booking-container">
        <?php if ($machine): ?>
            <div class="machine-details">
                <div class="machine-image">
                    <img src="<?php echo $machine['machine_images']; ?>" alt="<?php echo $machine['machine_name']; ?>">
                </div>
                <div class="machine-info">
                    <h2><?php echo $machine['machine_name']; ?></h2>
                    <p>Capacity: <?php echo $machine['capacity']; ?> kg</p>
                    <p>Price: <?php echo $machine['price_per_use']; ?> บาท</p>
                    <p>Washing Time: <?php echo $machine['washing_time']; ?> minutes</p>

                    <?php if ($machine['status'] === 'ว่าง'): ?>
                        <button 
                            onclick="confirmBooking('<?php echo htmlspecialchars($machine['machine_name'], ENT_QUOTES); ?>')" 
                            class="confirm-button"
                            type="button">
                            ชำระเงิน
                        </button>
                    <?php elseif ($machine['status'] === 'รอเริ่มทำงาน' && $machine['booking_username'] === $_SESSION['username'] && !$machine['start_time']): ?>
                        <p class="status-text">กรุณาเริ่มการซักผ้าที่หน้าเครื่อง</p>
                        <div id="bookingWindowTimer" class="timer-display">
                            เหลือเวลา: <span id="remainingBookingTime"><?php 
                                $minutes = floor($machine['remaining_minutes']);
                                echo sprintf('%2d', $minutes);
                            ?> </span> นาที
                        </div>
                        <button 
                            class="confirm-button"
                            onclick="window.location.href='machine_status.php?machine=<?php echo $machine['machine_name']; ?>&booking_reference=<?php echo $machine['booking_reference']; ?>'">
                            ไปที่หน้าเครื่อง
                        </button>
                    <?php elseif ($machine['usage_status'] === 'กำลังทำงาน'): ?>
                        <div class="machine-timer">
                            <p class="status-text">กำลังซักผ้า</p>
                            <p class="timer-text">เหลือเวลา: <?php 
                                $minutes = floor($machine['remaining_minutes']);
                                echo sprintf('%2d', $minutes);
                            ?>  นาที</p>
                        </div>
                    <?php else: ?>
                        <div class="machine-timer">
                            <p class="status-text"><?php echo $machine['status']; ?></p>
                            <?php if ($machine['remaining_minutes'] > 0): ?>
                                <p class="timer-text">เหลือเวลา: <span id="remainingTime"><?php 
                                    $minutes = floor($machine['remaining_minutes']);
                                    $seconds = floor(($machine['remaining_minutes'] - $minutes) * 60);
                                    echo sprintf('%2d', $minutes);
                                ?></span>  นาที</p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div id="paymentSlip" class="payment-slip" style="display: none;">
                <img src="logo.png" alt="Jamoon Laundry Logo" style="width: 200px; margin-bottom: 20px;">
                <h2>รายละเอียดการชำระเงิน</h2>
                <div id="bookingDetails"></div>
                <div class="action-buttons">
                    <button class="action-button cancel-button" onclick="hidePaymentSlip()">ยกเลิก</button>
                    <button class="action-button confirm-button" onclick="processPayment()">ยืนยันชำระเงิน</button>
                </div>
            </div>
        <?php else: ?>
            <div class="error-message">
                <p>ไม่พบข้อมูลเครื่องซักผ้า</p>
                <button onclick="location.href='dashboard.php'" class="return-button">กลับหน้าหลัก</button>
            </div>
        <?php endif; ?>
    </div>

<script>
const BOOKING_WINDOW = 30 * 60; // 30 minutes in seconds
let currentMachineName = null;
let currentBookingReference = null;
let bookingStartTime = null;

document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('remainingBookingTime')) {
        startBookingWindowTimer(<?php echo $machine['booking_timestamp'] ?? '0'; ?>);
    }
    setInterval(checkMachineStatus, 30000);
});

function confirmBooking(machineName) {
    document.getElementById('overlay').style.display = 'block';
    
    const paymentSlip = document.getElementById('paymentSlip');
    paymentSlip.innerHTML = `
        <div class="payment-header">
            <h2>รายละเอียดการชำระเงิน</h2>
        </div>
        <div class="payment-details">
            <p>เครื่องซักผ้า: ${machineName}</p>
            <p>น้ำหนัก: <?php echo $machine['capacity']; ?> kg</p>
            <p>ราคา: <?php echo $machine['price_per_use']; ?> บาท</p>
            <p>เวลาในการซักผ้า: <?php echo $machine['washing_time']; ?> นาที</p>
        </div>
        <div class="action-buttons">
            <button class="cancel-button" onclick="hidePaymentSlip()">ยกเลิก</button>
            <button class="confirm-button" onclick="showConfirmDialog('${machineName}')">ยืนยันชำระเงิน</button>
        </div>
    `;
    paymentSlip.style.display = 'block';
    paymentSlip.classList.add('fade-in');
}

function showConfirmDialog(machineName) {
    const confirmDialog = document.createElement('div');
    confirmDialog.className = 'confirm-dialog fade-in';
    confirmDialog.innerHTML = `
        <div class="confirm-content">
            <img src="logo.png" alt="Jamoon Laundry Logo">
            <p>คุณต้องการยืนยันการชำระเงินหรือไม่?</p>
            <div class="confirm-buttons">
                <button class="cancel-button" onclick="closeConfirmDialog()">ออก</button>
                <button class="confirm-button" onclick="processPayment('${machineName}')">ยืนยันชำระเงิน</button>
            </div>
        </div>
    `;
    document.body.appendChild(confirmDialog);
    document.getElementById('overlay').style.display = 'block';
}

function closeConfirmDialog() {
    const dialog = document.querySelector('.confirm-dialog');
    if (dialog) {
        dialog.classList.add('fade-out');
        setTimeout(() => dialog.remove(), 300);
    }
    document.getElementById('overlay').style.display = 'none';
}

function hidePaymentSlip() {
    document.getElementById('paymentSlip').style.display = 'none';
    document.getElementById('overlay').style.display = 'none';
}

function processPayment(machineName) {
    currentMachineName = machineName;
    const bookingReference = 'BOOK_' + Date.now();
    currentBookingReference = bookingReference;
    
    const formData = new FormData();
    formData.append('action', 'create_booking');
    formData.append('machine_name', machineName);
    formData.append('username', '<?php echo $_SESSION['username']; ?>');
    formData.append('booking_reference', bookingReference);

    document.getElementById('overlay').style.display = 'block';

    fetch('booking.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(result => {
        if (result.success) {
            bookingStartTime = result.booking_date; // Set booking start time
            const successMessage = document.createElement('div');
            successMessage.className = 'payment-slip fade-in';
            successMessage.innerHTML = `
                <div class="success-content">
                    <h2>ชำระเงินเรียบร้อย</h2>
                    <p>หมายเลขการจอง: ${bookingReference}</p>
                    <p>Username: <?php echo $_SESSION['username']; ?></p>
                    <p>เครื่องซักผ้า: ${machineName}</p>
                    <p>ราคา: <?php echo $machine['price_per_use']; ?> บาท</p>
                    <p>วันเวลาที่ชำระ: ${new Date().toLocaleString('th-TH')}</p>
                    <div id="bookingWindowTimer" class="timer-display"></div>
                    <div class="action-buttons">
                        <button class="cancel-button" onclick="exitToBooking()">ออก</button>
                        <button class="confirm-button" onclick="saveAndRedirect()">บันทึก</button>
                    </div>
                </div>
            `;
            document.body.appendChild(successMessage);
            document.getElementById('paymentSlip').style.display = 'none';
            document.getElementById('overlay').style.display = 'none';
            startBookingWindowTimer(bookingStartTime); // Start timer in success message
        } else {
            throw new Error(result.error || 'Booking failed');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('เกิดข้อผิดพลาด: ' + error.message);
        document.getElementById('overlay').style.display = 'none';
    });
}

function exitToBooking() {
    window.location.href = `booking.php?machine=${currentMachineName}`;
}

function saveAndRedirect() {
    window.location.href = `machine_status.php?machine=${currentMachineName}&booking_reference=${currentBookingReference}`;
}

function startBookingWindowTimer(startTime) {
    const timerDisplay = document.getElementById('bookingWindowTimer');
    const endTime = startTime + BOOKING_WINDOW;

    const interval = setInterval(() => {
        const currentTime = Math.floor(Date.now() / 1000);
        const timeLeft = Math.max(endTime - currentTime, 0);
        const minutes = Math.floor(timeLeft / 60); // Show only minutes
        timerDisplay.textContent = `เหลือเวลา: ${minutes} นาที`;

        if (timeLeft <= 0) {
            clearInterval(interval);
            timerDisplay.textContent = `เหลือเวลา: 0 นาที`;
        }
    }, 1000);
}

function checkMachineStatus() {
    const machineId = '<?php echo $machine['machine_name']; ?>';
    fetch('booking.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            'action': 'check_status',
            'machine_name': machineId
        })
    })
    .then(response => response.json())
    .then(data => {
        // No UI update needed; status handled on page load
    })
    .catch(error => console.error('Error:', error));
}
</script>
</body>
</html>