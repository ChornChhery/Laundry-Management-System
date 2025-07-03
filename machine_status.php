<?php
session_start();
require_once("connectDB.php");

if (!isset($pdo)) {
    die("Database connection not established.");
}

// Get user profile
$stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
$stmt->execute([$_SESSION['username']]);
$user = $stmt->fetch();

// Get machine and booking details
$machine_name = $_GET['machine'] ?? null;
$booking_reference = $_GET['booking_reference'] ?? null;

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
            UNIX_TIMESTAMP(COALESCE(b.end_time, NOW())) AS end_timestamp,
            UNIX_TIMESTAMP(COALESCE(b.booking_date, NOW())) AS booking_timestamp,
            CASE 
                WHEN b.usage_status = 'รอเริ่มทำงาน' THEN 
                    GREATEST(TIMESTAMPDIFF(MINUTE, NOW(), DATE_ADD(COALESCE(b.booking_date, NOW()), INTERVAL 30 MINUTE)), 0)
                WHEN b.usage_status = 'กำลังทำงาน' THEN 
                    GREATEST(TIMESTAMPDIFF(MINUTE, NOW(), COALESCE(b.end_time, NOW())), 0)
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

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    try {
        $pdo->beginTransaction();
        
        switch ($_POST['action']) {
            case 'start_washing':
                $username = $_POST['username'] ?? '';
                $stmt = $pdo->prepare("
                    SELECT usage_status, booking_date, username 
                    FROM bookings 
                    WHERE booking_reference = ? 
                    AND username = ?
                    FOR UPDATE
                ");
                $stmt->execute([$_POST['booking_reference'], $username]); // Use POST username
                $booking = $stmt->fetch();

                if ($booking && 
                    $booking['usage_status'] === 'รอเริ่มทำงาน' && 
                    strtotime($booking['booking_date']) + (30 * 60) > time()) {
                    
                    $stmt = $pdo->prepare("
                        UPDATE bookings 
                        SET start_time = NOW(),
                            end_time = DATE_ADD(NOW(), INTERVAL ? MINUTE),
                            usage_status = 'กำลังทำงาน'
                        WHERE booking_reference = ? 
                        AND username = ?
                    ");
                    $result = $stmt->execute([
                        $_POST['washing_time'],
                        $_POST['booking_reference'],
                        $username // Use POST username
                    ]);
                    
                    if ($result) {
                        $stmt = $pdo->prepare("
                            UPDATE washing_machines 
                            SET status = 'กำลังใช้งาน'
                            WHERE machine_name = ?
                        ");
                        $stmt->execute([$_POST['machine_name']]);
                    }
                    
                    $pdo->commit();
                    echo json_encode(['success' => true, 'washing_time' => $_POST['washing_time']]);
                } else {
                    throw new Exception('Invalid booking status, expired time window, or unauthorized user');
                }
                break;

            case 'handle_expired_booking':
                $stmt = $pdo->prepare("
                    UPDATE bookings b
                    JOIN washing_machines w ON b.machine_name = w.machine_name
                    SET b.usage_status = CASE 
                            WHEN b.start_time IS NULL THEN 'ไม่เสร็จ'
                            ELSE 'เสร็จสิ้น'
                        END,
                        w.status = 'ว่าง'
                    WHERE b.booking_reference = ?
                    AND b.usage_status IN ('รอเริ่มทำงาน', 'กำลังทำงาน')
                ");
                $stmt->execute([$_POST['booking_reference']]);
                
                $pdo->commit();
                echo json_encode(['success' => true]);
                break;

            case 'get_status':
                $stmt = $pdo->prepare("
                    SELECT 
                        w.status,
                        b.booking_reference,
                        b.username AS booking_username,
                        b.start_time,
                        b.end_time,
                        b.usage_status,
                        UNIX_TIMESTAMP(COALESCE(b.end_time, NOW())) AS end_timestamp,
                        UNIX_TIMESTAMP(COALESCE(b.booking_date, NOW())) AS booking_timestamp,
                        TIMESTAMPDIFF(MINUTE, NOW(), COALESCE(b.end_time, NOW())) AS washing_remaining,
                        TIMESTAMPDIFF(MINUTE, NOW(), DATE_ADD(COALESCE(b.booking_date, NOW()), INTERVAL 30 MINUTE)) AS booking_remaining
                    FROM washing_machines w
                    LEFT JOIN bookings b ON w.machine_name = b.machine_name 
                        AND b.usage_status IN ('รอเริ่มทำงาน', 'กำลังทำงาน')
                    WHERE w.machine_name = ?
                ");
                $stmt->execute([$_POST['machine_name']]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($result === false) {
                    $result = [
                        'status' => 'ว่าง',
                        'booking_reference' => null,
                        'booking_username' => null,
                        'start_time' => null,
                        'end_time' => null,
                        'usage_status' => null,
                        'end_timestamp' => 0,
                        'booking_timestamp' => 0,
                        'washing_remaining' => 0,
                        'booking_remaining' => 0
                    ];
                }
                
                echo json_encode($result);
                break;

            case 'update_machine_status':
                $stmt = $pdo->prepare("
                    UPDATE bookings 
                    SET usage_status = 'เสร็จสิ้น'
                    WHERE end_time <= NOW() 
                    AND usage_status = 'กำลังทำงาน'
                ");
                $stmt->execute();

                $stmt = $pdo->prepare("
                    UPDATE washing_machines w
                    SET status = 'ว่าง'
                    WHERE machine_name IN (
                        SELECT machine_name 
                        FROM bookings 
                        WHERE end_time <= DATE_SUB(NOW(), INTERVAL 2 MINUTE)
                        AND usage_status = 'เสร็จสิ้น'
                    )
                ");
                $stmt->execute();
                
                $pdo->commit();
                echo json_encode(['success' => true]);
                break;
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
    exit;
}

// Auto-update expired bookings
// $stmt = $pdo->prepare("
//     UPDATE bookings b
//     JOIN washing_machines w ON b.machine_name = w.machine_name
//     SET b.usage_status = 'ไม่เสร็จ',
//         w.status = 'ว่าง'
//     WHERE b.usage_status = 'รอเริ่มทำงาน'
//     AND b.start_time IS NULL 
//     AND TIMESTAMPDIFF(MINUTE, b.booking_date, NOW()) > 30
// ");
// $stmt->execute();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Machine Status - Jamoon Laundry</title>
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
                    
                    <?php if ($machine['booking_username'] === $_SESSION['username'] && $machine['usage_status'] === 'รอเริ่มทำงาน' && !$machine['start_time'] && $machine['remaining_minutes'] > 0): ?>
                        <div id="bookingWindowTimer" class="timer-display">
                            เหลือเวลา: <span id="remainingBookingTime"><?php 
                                $minutes = floor($machine['remaining_minutes']);
                                echo sprintf('%2d', $minutes);
                            ?></span> นาที
                        </div>
                        <button id="startWashing" 
                                class="confirm-button"
                                onclick="startWashingTimer('<?php echo $machine['machine_name']; ?>', '<?php echo $booking_reference; ?>')">
                            เริ่มซักผ้า
                        </button>
                    <?php elseif ($machine['usage_status'] === 'กำลังทำงาน'): ?>
                        <div class="machine-timer">
                            <p class="status-text">กำลังซักผ้า</p>
                            <p class="timer-text">เหลือเวลา: <span id="remainingTime"><?php 
                                $minutes = floor($machine['remaining_minutes']);
                                echo sprintf('%2d', $minutes);
                            ?> </span></p>
                        </div>
                    <?php else: ?>
                        <div class="machine-timer">
                            <p class="status-text"><?php echo $machine['status']; ?></p>
                            <?php if ($machine['remaining_minutes'] > 0): ?>
                                <p class="timer-text">เหลือเวลา: <span id="remainingTime"><?php 
                                    $minutes = floor($machine['remaining_minutes']);
                                    echo sprintf('%2d', $minutes);
                                ?></span></p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
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
const RESET_WINDOW = 2 * 60; // 2 minutes in seconds

document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('startWashing')) {
        startBookingWindowTimer();
    } else if (document.getElementById('remainingTime')) {
        startWashingCountdown();
    }
    setInterval(checkMachineStatus, 1000); // Frequent checks for status updates
});

function startBookingWindowTimer() {
    const timerDisplay = document.getElementById('bookingWindowTimer');
    const startButton = document.getElementById('startWashing');
    const bookingStartTime = <?php echo $machine['booking_timestamp'] ?? 'Math.floor(Date.now() / 1000)'; ?>;
    const bookingEndTime = bookingStartTime + BOOKING_WINDOW;

    const bookingInterval = setInterval(() => {
        const currentTime = Math.floor(Date.now() / 1000);
        const timeLeft = Math.max(bookingEndTime - currentTime, 0);
        const minutes = Math.floor(timeLeft / 60); // Show only minutes
        timerDisplay.textContent = `เหลือเวลา: ${minutes} นาที`;

        if (timeLeft <= 0) {
            clearInterval(bookingInterval);
            timerDisplay.textContent = `เหลือเวลา: 0 นาที`;
            startButton.disabled = true;
            startResetPeriod('<?php echo $machine['machine_name']; ?>', '<?php echo $booking_reference; ?>');
        }
    }, 1000);
}

function startWashingTimer(machineName, bookingReference) {
    const startButton = document.getElementById('startWashing');
    if (startButton) {
        startButton.disabled = true;
        startButton.style.display = 'none'; // Hide button permanently
    }

    const formData = new URLSearchParams();
    formData.append('action', 'start_washing');
    formData.append('machine_name', machineName);
    formData.append('booking_reference', bookingReference);
    formData.append('washing_time', <?php echo $machine['washing_time']; ?>);
    formData.append('username', '<?php echo $_SESSION['username']; ?>'); // Add username to verify

    fetch('machine_status.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: formData
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            window.location.reload(); // Refresh to update UI
        } else {
            alert('ไม่สามารถเริ่มการซักผ้าได้: ' + (result.error || 'เกิดข้อผิดพลาด'));
            if (startButton) {
                startButton.disabled = false;
                startButton.style.display = 'block';
            }
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('เกิดข้อผิดพลาด: ' + error.message);
        if (startButton) {
            startButton.disabled = false;
            startButton.style.display = 'block';
        }
    });
}

function startWashingCountdown() {
    const endTime = <?php echo $machine['end_timestamp'] ?? 0; ?>;
    const timerDisplay = document.getElementById('remainingTime');
    if (!timerDisplay || endTime === 0) return;

    const washingInterval = setInterval(() => {
        const currentTime = Math.floor(Date.now() / 1000);
        const timeLeft = Math.max(endTime - currentTime, 0);
        const minutes = Math.floor(timeLeft / 60);
        const seconds = timeLeft % 60;
        timerDisplay.textContent = `${minutes} นาที`;

        if (timeLeft <= 0) {
            clearInterval(washingInterval);
            startResetPeriod('<?php echo $machine['machine_name']; ?>', '<?php echo $booking_reference; ?>');
        }
    }, 1000);
}

function startResetPeriod(machineName, bookingReference) {
    const timerDisplay = document.querySelector('.machine-timer');
    let resetTimeLeft = RESET_WINDOW;

    const resetInterval = setInterval(() => {
        const minutes = Math.floor(resetTimeLeft / 60);
        const seconds = resetTimeLeft % 60;
        if (resetTimeLeft > 0) {
            timerDisplay.innerHTML = `
                <p class="status-text">กำลังรีเซ็ตเครื่อง</p>
                <p class="timer-text">เหลือเวลา: ${minutes}:${seconds.toString().padStart(2, '0')}</p>
            `;
        } else {
            timerDisplay.innerHTML = `
                <p class="status-text">กำลังรีเซ็ตเครื่อง</p>
                <p class="timer-text">เหลือเวลา: 0:00</p>
            `;
        }

        if (resetTimeLeft <= 0) {
            clearInterval(resetInterval);
            const formData = new URLSearchParams();
            formData.append('action', 'handle_expired_booking');
            formData.append('machine_name', machineName);
            formData.append('booking_reference', bookingReference);

            fetch('machine_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: formData
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    // Update UI to reflect machine status as 'ว่าง'
                    const machineInfo = document.querySelector('.machine-info');
                    if (machineInfo) {
                        machineInfo.innerHTML = `
                            <h2><?php echo $machine['machine_name']; ?></h2>
                            <p>Capacity: <?php echo $machine['capacity']; ?> kg</p>
                            <p>Price: <?php echo $machine['price_per_use']; ?> บาท</p>
                            <p>Washing Time: <?php echo $machine['washing_time']; ?> minutes</p>
                            <div class="machine-timer">
                                <p class="status-text">ว่าง</p>
                            </div>
                        `;
                    }
                    // Reload the page to ensure the latest status is reflected
                    window.location.reload();
                }
            })
            .catch(error => console.error('Error updating machine status:', error));
        }
        resetTimeLeft--;
    }, 1000);
}

function checkMachineStatus() {
    const machineId = '<?php echo $machine['machine_name']; ?>';
    fetch('machine_status.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            'action': 'get_status',
            'machine_name': machineId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'ว่าง' && !document.getElementById('startWashing')) {
            window.location.reload(); // Refresh only if not in booking phase
        }
    })
    .catch(error => console.error('Error:', error));
}
</script>
</body>
</html>