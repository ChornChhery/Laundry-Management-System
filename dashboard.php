<?php
session_start();
require_once("connectDB.php"); // ใช้ PDO

// Handle GET request for machine details for edit machine
if (isset($_GET['get_machine']) || isset($_FILES['machine_image'])) {
    header('Content-Type: application/json; charset=utf-8');
}

if (isset($_GET['get_machine'])) {
    header('Content-Type: application/json');
    try {
        $stmt = $pdo->prepare("
            SELECT 
                w.machine_name,
                w.serial_number,
                w.machine_images,
                m.capacity,
                m.price_per_use,
                m.washing_time
            FROM washing_machines w
            JOIN machine_types m ON w.serial_number = m.serial_number
            WHERE w.machine_name = ?
        ");
        $stmt->execute([$_GET['get_machine']]);
        $machine = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($machine) {
            echo json_encode($machine);
        } else {
            echo json_encode(['error' => 'Machine not found']);
        }
    } catch (PDOException $e) {
        echo json_encode(['error' => 'Database error']);
    }
    exit;
}



if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'delete':
                try {
                    // Start transaction
                    $pdo->beginTransaction();
                    
                    // Get serial number before deletion
                    $stmt = $pdo->prepare("SELECT serial_number FROM washing_machines WHERE machine_name = ?");
                    $stmt->execute([$_POST['machine_name']]);
                    $serial = $stmt->fetchColumn();
                    
                    // Delete related bookings first
                    $stmt = $pdo->prepare("DELETE FROM bookings WHERE machine_name = ?");
                    $stmt->execute([$_POST['machine_name']]);
                    
                    // Delete from washing_machines
                    $stmt = $pdo->prepare("DELETE FROM washing_machines WHERE machine_name = ?");
                    $stmt->execute([$_POST['machine_name']]);
                    
                    // Delete from machine_types
                    $stmt = $pdo->prepare("DELETE FROM machine_types WHERE serial_number = ?");
                    $stmt->execute([$serial]);
                    
                    // Delete machine image file
                    $stmt = $pdo->prepare("SELECT machine_images FROM washing_machines WHERE machine_name = ?");
                    $stmt->execute([$_POST['machine_name']]);
                    $imagePath = $stmt->fetchColumn();
                    if ($imagePath && file_exists($imagePath)) {
                        unlink($imagePath);
                    }
                    
                    $pdo->commit();
                    echo json_encode(['success' => true]);
                } catch (Exception $e) {
                    $pdo->rollBack();
                    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                }
                exit;
            case 'toggle_status':
                $stmt = $pdo->prepare("SELECT status FROM washing_machines WHERE machine_name = ?");
                $stmt->execute([$_POST['machine_name']]);
                $currentStatus = $stmt->fetch(PDO::FETCH_ASSOC)['status'];
                
                $newStatus = ($currentStatus === 'ว่าง') ? 'กำลังใช้งาน' : 'ว่าง';
                
                $stmt = $pdo->prepare("UPDATE washing_machines SET status = ? WHERE machine_name = ?");
                $stmt->execute([$newStatus, $_POST['machine_name']]);
                
                echo json_encode(['success' => true]);
                exit;
            
            case 'edit':
                try {
                    $oldMachineName = $_POST['old_machine_name'];
                    $newMachineName = $_POST['machine_name'];
                    $serialNumber = $_POST['serial_number'];

                    // Check for duplicate machine_name (excluding the current machine if editing)
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM washing_machines WHERE machine_name = ? AND machine_name != ?");
                    $stmt->execute([$newMachineName, $oldMachineName]);
                    if ($stmt->fetchColumn() > 0) {
                        echo json_encode(['success' => false, 'error' => 'Machine name already exists']);
                        exit;
                    }

                    // Check for duplicate serial_number (excluding the current machine if editing)
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM washing_machines w 
                                        JOIN machine_types m ON w.serial_number = m.serial_number 
                                        WHERE m.serial_number = ? AND w.machine_name != ?");
                    $stmt->execute([$serialNumber, $oldMachineName]);
                    if ($stmt->fetchColumn() > 0) {
                        echo json_encode(['success' => false, 'error' => 'Serial number already exists']);
                        exit;
                    }

                    // Update machine_types table
                    $stmt1 = $pdo->prepare("UPDATE machine_types SET 
                        serial_number = ?,
                        capacity = ?,
                        price_per_use = ?,
                        washing_time = ?
                        WHERE serial_number = (
                            SELECT serial_number FROM washing_machines 
                            WHERE machine_name = ?
                        )");
                    
                    $stmt1->execute([
                        $_POST['serial_number'],
                        $_POST['capacity'],
                        $_POST['price_per_use'],
                        $_POST['washing_time'],
                        $oldMachineName
                    ]);
                    
                    // Update washing_machines table
                    $stmt2 = $pdo->prepare("UPDATE washing_machines SET 
                        machine_name = ?,
                        serial_number = ?
                        WHERE machine_name = ?");
                    
                    $stmt2->execute([
                        $_POST['machine_name'],
                        $_POST['serial_number'],
                        $oldMachineName
                    ]);
                    
                    if (isset($_FILES['machine_image']) && $_FILES['machine_image']['size'] > 0) {
                        $targetDir = "assets/images/machines/";
                        $fileName = time() . '_' . basename($_FILES["machine_image"]["name"]);
                        $targetFilePath = $targetDir . $fileName;
                        
                        if (move_uploaded_file($_FILES["machine_image"]["tmp_name"], $targetFilePath)) {
                            $stmt3 = $pdo->prepare("UPDATE washing_machines SET 
                                machine_images = ? 
                                WHERE machine_name = ?");
                            $stmt3->execute([$targetFilePath, $_POST['machine_name']]);
                        }
                    }
                    
                    echo json_encode(['success' => true]);
                    exit;
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                    exit;
                }
               

            case 'add_machine':
                try {
                    $pdo->beginTransaction();
                    
                    $machineName = $_POST['machine_name'];
                    $serialNumber = $_POST['serial_number'];

                    // Check for duplicate machine_name
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM washing_machines WHERE machine_name = ?");
                    $stmt->execute([$machineName]);
                    if ($stmt->fetchColumn() > 0) {
                        echo json_encode(['success' => false, 'error' => 'Machine name already exists']);
                        exit;
                    }

                    // Check for duplicate serial_number
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM machine_types WHERE serial_number = ?");
                    $stmt->execute([$serialNumber]);
                    if ($stmt->fetchColumn() > 0) {
                        echo json_encode(['success' => false, 'error' => 'Serial number already exists']);
                        exit;
                    }

                    $targetDir = "assets/images/machines/";
                    if (!file_exists($targetDir)) {
                        mkdir($targetDir, 0777, true);
                    }
                    
                    $fileName = time() . '_' . basename($_FILES["machine_image"]["name"]);
                    $targetFilePath = $targetDir . $fileName;
                    
                    if (move_uploaded_file($_FILES["machine_image"]["tmp_name"], $targetFilePath)) {
                        $stmt1 = $pdo->prepare("INSERT INTO machine_types (serial_number, capacity, price_per_use, washing_time) VALUES (?, ?, ?, ?)");
                        $stmt1->execute([
                            $_POST['serial_number'],
                            $_POST['capacity'],
                            $_POST['price_per_use'],
                            $_POST['washing_time']
                        ]);
                        
                        $stmt2 = $pdo->prepare("INSERT INTO washing_machines (machine_name, serial_number, machine_images, status) VALUES (?, ?, ?, 'ว่าง')");
                        $stmt2->execute([
                            $_POST['machine_name'],
                            $_POST['serial_number'],
                            $targetFilePath
                        ]);
                        
                        $pdo->commit();
                        echo json_encode(['success' => true]);
                    } else {
                        throw new Exception('Failed to upload image');
                    }
                } catch (Exception $e) {
                    $pdo->rollBack();
                    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                }
                exit;
             
        }
    }
}


// Function to handle database errors
function handleDatabaseError($e) {
    error_log("Database Error: " . $e->getMessage());
    return "An error occurred. Please try again later.";
}

// Check session
if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit();
}

// Check if user is admin
$isAdmin = ($_SESSION['username'] === 'admin@jamoon');

// Fetch machines with details using prepared statement
try {
    $stmt = $pdo->prepare("SELECT 
        w.*, 
        m.capacity,
        m.price_per_use,
        m.washing_time
    FROM washing_machines w
    JOIN machine_types m ON w.serial_number = m.serial_number");
    $stmt->execute();
    $machines = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    handleDatabaseError($e);
    $machines = [];
}

// Admin specific data
if ($isAdmin) {
    try {
        // Get total revenue with prepared statement
        $revenueStmt = $pdo->prepare("SELECT SUM(m.price_per_use) as total_revenue 
            FROM bookings b
            JOIN washing_machines w ON b.machine_name = w.machine_name
            JOIN machine_types m ON w.serial_number = m.serial_number
            WHERE b.usage_status = 'เสร้จสิ้น'");
        $revenueStmt->execute();
        $revenue = $revenueStmt->fetch(PDO::FETCH_ASSOC);

        // Get total users with prepared statement
        $userStmt = $pdo->prepare("SELECT COUNT(*) as total_users 
            FROM users 
            WHERE username != 'admin@jamoon'");
        $userStmt->execute();
        $userCount = $userStmt->fetch(PDO::FETCH_ASSOC);

        // Get today's bookings with prepared statement
        $todayBookings = $pdo->prepare("SELECT COUNT(*) as today_bookings 
            FROM bookings 
            WHERE DATE(booking_date) = CURDATE()");
        $todayBookings->execute();
        $bookingCount = $todayBookings->fetch(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        handleDatabaseError($e);
        $revenue = ['total_revenue' => 0];
        $userCount = ['total_users' => 0];
        $bookingCount = ['today_bookings' => 0];
    }
}

// Function to update machine status
function updateMachineStatus($pdo, $machineName, $status) {
    try {
        $stmt = $pdo->prepare("UPDATE washing_machines 
            SET status = ? 
            WHERE machine_name = ?");
        return $stmt->execute([$status, $machineName]);
    } catch (PDOException $e) {
        handleDatabaseError($e);
        return false;
    }
}

// Function to validate machine data
function validateMachineData($data) {
    $errors = [];
    if (empty($data['machine_name'])) $errors[] = "Machine name is required";
    if (empty($data['serial_number'])) $errors[] = "Serial number is required";
    if (!is_numeric($data['capacity']) || $data['capacity'] <= 0) $errors[] = "Invalid capacity value";
    if (!is_numeric($data['price_per_use']) || $data['price_per_use'] <= 0) $errors[] = "Invalid price value";
    return $errors;
}

//Edit Machine 
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'edit':
                try {
                    $oldMachineName = $_POST['old_machine_name'];
                    
                    // Update machine_types table
                    $stmt1 = $pdo->prepare("UPDATE machine_types SET 
                        serial_number = ?,
                        capacity = ?,
                        price_per_use = ?,
                        washing_time = ?
                        WHERE serial_number = (
                            SELECT serial_number FROM washing_machines 
                            WHERE machine_name = ?
                        )");
                    
                    $stmt1->execute([
                        $_POST['serial_number'],
                        $_POST['capacity'],
                        $_POST['price_per_use'],
                        $_POST['washing_time'],
                        $oldMachineName
                    ]);
                    
                    // Update washing_machines table
                    $stmt2 = $pdo->prepare("UPDATE washing_machines SET 
                        machine_name = ?,
                        serial_number = ?
                        WHERE machine_name = ?");
                    
                    $stmt2->execute([
                        $_POST['machine_name'],
                        $_POST['serial_number'],
                        $oldMachineName
                    ]);
                    
                    if (isset($_FILES['machine_image']) && $_FILES['machine_image']['size'] > 0) {
                        $targetDir = "assets/images/machines/";
                        $fileName = time() . '_' . basename($_FILES["machine_image"]["name"]);
                        $targetFilePath = $targetDir . $fileName;
                        
                        if (move_uploaded_file($_FILES["machine_image"]["tmp_name"], $targetFilePath)) {
                            $stmt3 = $pdo->prepare("UPDATE washing_machines SET 
                                machine_images = ? 
                                WHERE machine_name = ?");
                            $stmt3->execute([$targetFilePath, $_POST['machine_name']]);
                        }
                    }
                    
                    echo json_encode(['success' => true]);
                    exit;
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                    exit;
                }
              
            case 'add_machine':
                try {
                    $pdo->beginTransaction();
                    
                    $machineName = $_POST['machine_name'];
                    $serialNumber = $_POST['serial_number'];

                    // Check for duplicate machine_name
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM washing_machines WHERE machine_name = ?");
                    $stmt->execute([$machineName]);
                    if ($stmt->fetchColumn() > 0) {
                        echo json_encode(['success' => false, 'error' => 'Machine name already exists']);
                        exit;
                    }

                    // Check for duplicate serial_number
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM machine_types WHERE serial_number = ?");
                    $stmt->execute([$serialNumber]);
                    if ($stmt->fetchColumn() > 0) {
                        echo json_encode(['success' => false, 'error' => 'Serial number already exists']);
                        exit;
                    }

                    $targetDir = "assets/images/machines/";
                    if (!file_exists($targetDir)) {
                        mkdir($targetDir, 0777, true);
                    }
                    
                    $fileName = time() . '_' . basename($_FILES["machine_image"]["name"]);
                    $targetFilePath = $targetDir . $fileName;
                    
                    if (move_uploaded_file($_FILES["machine_image"]["tmp_name"], $targetFilePath)) {
                        $stmt1 = $pdo->prepare("INSERT INTO machine_types (serial_number, capacity, price_per_use, washing_time) VALUES (?, ?, ?, ?)");
                        $stmt1->execute([
                            $_POST['serial_number'],
                            $_POST['capacity'],
                            $_POST['price_per_use'],
                            $_POST['washing_time']
                        ]);
                        
                        $stmt2 = $pdo->prepare("INSERT INTO washing_machines (machine_name, serial_number, machine_images, status) VALUES (?, ?, ?, 'ว่าง')");
                        $stmt2->execute([
                            $_POST['machine_name'],
                            $_POST['serial_number'],
                            $targetFilePath
                        ]);
                        
                        $pdo->commit();
                        echo json_encode(['success' => true]);
                    } else {
                        throw new Exception('Failed to upload image');
                    }
                } catch (Exception $e) {
                    $pdo->rollBack();
                    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                }
                exit;
               
        }
    }
}

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
    <title>Jamoon Laundry</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
    <link rel = "icon" href="logo.png">
    <link rel="stylesheet" href="style1.css">
</head>

<body>

    <!-- Header Section - Same for both admin and user -->
    <header class="header">
        <div class="logo">
            <img src="logo.png" alt="Jamoon Laundry">
        </div>
        <nav class="nav-menu">
            <?php if ($isAdmin): ?>
                <a href="dashboard.php">Home</a>
                <a href="admin.php#machines">Machines</a>
                <a href="admin.php#users">Users</a>
                <a href="admin.php#reports">Reports</a>
            <?php else: ?>
                <a href="dashboard.php">Home</a>
                <a href="about.php">About Us</a>
                <a href="about.php">Contact Us</a>
                <a href="history.php">History</a>
            <?php endif; ?>
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

    <?php if ($isAdmin): ?>
    <!-- Admin Dashboard -->
    <div class="admin-dashboard">
        <!-- Stats Section -->
        <div class="stats-section">
            <div class="stat-card">
                <h3>Total Revenue</h3>
                <div class="value"><?php echo number_format($revenue['total_revenue']); ?> ฿</div>
            </div>
            <div class="stat-card">
                <h3>Total Users</h3>
                <div class="value"><?php echo $userCount['total_users']; ?></div>
            </div>
            <div class="stat-card">
                <h3>Today's Bookings</h3>
                <div class="value"><?php echo $bookingCount['today_bookings']; ?></div>
            </div>
        </div>
    <?php endif; ?>

    <div class="dashboard-container">
        <?php if ($isAdmin): ?>
            <!-- Machine Management Section -->
            <div class="machine-management">
                <div class="section-header">
                    <h2>Machine Management</h2>
                    <button onclick="showAddMachineModal()" class="admin-button">Add New Machine</button>
                </div>
            </div>

            <!-- Admin Machine Grid -->
            <div class="machines-grid">
                <?php foreach ($machines as $machine): ?>
                    <div class="machine-card" data-machine="<?php echo $machine['machine_name']; ?>" data-status="<?php echo $machine['status']; ?>">
                        <p class="machine-status <?php echo $machine['status'] === 'ว่าง' ? 'status-availables' : 'status-in-uses'; ?>">
                                    <?php echo $machine['status']; ?>
                        </p>
                        <img src="<?php echo $machine['machine_images']; ?>" alt="<?php echo $machine['machine_name']; ?>" class="machine-image">
                        <h2><?php echo $machine['machine_name']; ?></h2>
                        <div class="machine-info">
                            <p>Serial: <?php echo $machine['serial_number']; ?></p>
                            <p>Capacity: <?php echo $machine['capacity']; ?> kg</p>
                            <p>Price: <?php echo $machine['price_per_use']; ?> ฿</p>
                            <p>Time: <?php echo $machine['washing_time']; ?> min</p>
                    </div>
                    
                    <div class="admin-controls">
                        <button onclick="editMachine('<?php echo $machine['machine_name']; ?>')" class="admin-button edit-btn">Edit</button>
                        <button onclick="toggleMachineStatus('<?php echo $machine['machine_name']; ?>')" class="admin-button status-btn">Change Status</button>
                        <button onclick="deleteMachine('<?php echo $machine['machine_name']; ?>')" class="admin-button delete-btn">Delete</button>
                    </div>

                </div>
                <?php endforeach; ?>
            </div>

            <!-- Add Machine Modal -->
            <div id="addMachineModal" class="modal">
                <div class="modal-content">
                    <span class="close" onclick="closeAddMachineModal()">×</span>
                    <h2>Add New Machine</h2>
                    
                    <form id="addMachineForm" method="POST" action="dashboard.php" enctype="multipart/form-data">
                        <div class="form-group">
                            <label for="machine_name">Machine Name</label>
                            <input type="text" id="machine_name" name="machine_name" required>
                        </div>

                        <div class="form-group">
                            <label for="serial_number">Serial Number</label>
                            <input type="text" id="serial_number" name="serial_number" required>
                        </div>

                        <div class="form-group">
                            <label for="capacity">Capacity (KG)</label>
                            <input type="text" id="capacity" name="capacity" required>
                        </div>

                        <div class="form-group">
                            <label for="price_per_use">Price per Use (THB)</label>
                            <input type="text" id="price_per_use" name="price_per_use" required>
                        </div>

                        <div class="form-group">
                            <label for="washing_time">Washing Time (Minutes)</label>
                            <input type="text" id="washing_time" name="washing_time" required>
                        </div>

                        <div class="form-group file-upload">
                            <label class="file-upload-label">
                                <span class="file-upload-icon">📁</span>
                                <span class="file-upload-text">Upload Machine Photo</span>
                                <input type="file" name="machine_image" accept="image/*" required style="display: none;">
                            </label>
                            <div class="selected-file-name"></div>
                            <div class="image-preview">
                                <img src="" alt="Preview">
                                <!-- <button type="button" class="remove-image">×</button> -->
                            </div>
                        </div>

                        <div class="modal-buttons">
                            <button type="button" class="cancel-btn" onclick="closeAddMachineModal()">Cancel</button>
                            <button type="submit" class="submit-btn">Add Machine</button>
                        </div>
                    </form>
                </div>
            </div>

        </div>

        <?php else: ?>
        <!-- User Dashboard -->
        <div class="user-dashboard">
            <!-- Filter Section -->
            <div class="filter-section">
                <button class="filter-button active" data-filter="all" onclick="filterMachines('all')">View All</button>
                <button class="filter-button" data-filter="เครื่องว่าง" onclick="filterMachines('เครื่องว่าง')">ว่าง</button>
                <button class="filter-button" data-filter="กำลังใช้งาน" onclick="filterMachines('กำลังใช้งาน')">กำลังใช้งาน</button>

                <div class="search-section">
                    <input type="text" class="search-input" placeholder="Search machine capacity">
                    <button class="filter-button" onclick="searchMachines()">Search</button>
                </div>
            </div>

            <!-- User Machine Grid -->
            <div class="machines-grid">
                <?php foreach ($machines as $machine): ?>
                <div class="machine-card" data-status="<?php echo $machine['status']; ?>" data-capacity="<?php echo $machine['capacity']; ?>">
                    <a href="booking.php?machine=<?php echo urlencode($machine['machine_name']); ?>" class="machine-link">
                        <p class="machine-status <?php echo $machine['status'] === 'ว่าง' ? 'status-available' : 'status-in-use'; ?>">
                            <?php echo $machine['status']; ?>
                        </p>
                        <img src="<?php echo $machine['machine_images']; ?>" alt="<?php echo $machine['machine_name']; ?>" class="machine-image">
                        <h3><?php echo $machine['machine_name']; ?></h3>
                        <p>Capacity: <?php echo $machine['capacity']; ?> kg</p>
                        <p>Price: <?php echo $machine['price_per_use']; ?> บาท</p>
                        
                        <?php if ($machine['status'] === 'ว่าง'): ?>
                            <button class="booking-button">View Details</button>
                        <?php endif; ?>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>

                        
           
                        
            <!-- Checkbox ซ่อนอยู่ -->
            <input type="checkbox" id="toggle-contact">

            <!-- ปุ่มหลัก + ข้อความ -->
            <div class="contact-container">
                <label for="toggle-contact" class="contact-text">ข้อมูลติดต่อ My Admin</label>
                <label for="toggle-contact" class="contact-btn">
                    <span class="material-icons">contact_page</span>
                </label>

            </div>

            <!-- เมนูโซเชียล -->
            <div class="contact-menu">
                <a href="about.php" class="contact-item contact-us">
                    <img src="jame.JPG" alt="Contact Icon" class="contact-icon">
                    <span>Admin Jame</span>
                </a>

                <a href="about.php" class="contact-item contact-us">
                    <img src="moon.JPG" alt="Contact Icon" class="contact-icon">
                    <span>Admin Moon</span>
                </a>
            </div>



            <!-- Booking Modal -->
            <div id="bookingModal" class="booking-modal">
                <div class="modal-content">
                    <span class="close-modal" onclick="closeBookingModal()">×</span>
                    <div id="bookingDetails"></div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>


<script>
    // Admin Functions
    <?php if ($isAdmin): ?>
        function editMachine(machineName) {
            fetch(`dashboard.php?get_machine=${encodeURIComponent(machineName)}`, {
                headers: {
                    'Accept': 'application/json'
                }
            })
            .then(response => response.json())
            .then(machine => {
                if (machine.error) {
                    throw new Error(machine.error);
                }
                
                const form = document.getElementById('addMachineForm');
                // Update form attributes for file upload
                form.setAttribute('enctype', 'multipart/form-data');
                form.setAttribute('method', 'POST');
                
                form.innerHTML = `
                    <div class="form-group">
                        <label for="machine_name">Machine Name</label>
                        <input type="text" id="machine_name" name="machine_name" value="${machine.machine_name}" required>
                    </div>

                    <div class="form-group">
                        <label for="serial_number">Serial Number</label>
                        <input type="text" id="serial_number" name="serial_number" value="${machine.serial_number}" required>
                    </div>

                    <div class="form-group">
                        <label for="capacity">Capacity (KG)</label>
                        <input type="text" id="capacity" name="capacity" value="${machine.capacity}" required>
                    </div>

                    <div class="form-group">
                        <label for="price_per_use">Price per Use (THB)</label>
                        <input type="text" id="price_per_use" name="price_per_use" value="${machine.price_per_use}" required>
                    </div>

                    <div class="form-group">
                        <label for="washing_time">Washing Time (Minutes)</label>
                        <input type="text" id="washing_time" name="washing_time" value="${machine.washing_time}" required>
                    </div>

                    <div class="form-group file-upload">
                        <label class="file-upload-label">
                            <span class="file-upload-icon">📁</span>
                            <span class="file-upload-text">Change Machine Photo</span>
                            <input type="file" name="machine_image" accept="image/*" style="display: none;">
                        </label>
                        <div class="selected-file-name"></div>
                        <div class="image-preview">
                            <img src="${machine.machine_images}" alt="Preview">
                        </div>
                    </div>

                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="old_machine_name" value="${machine.machine_name}">
                    <input type="hidden" name="current_image" value="${machine.machine_images}">

                    <div class="modal-buttons">
                        <button type="button" class="cancel-btn" onclick="closeAddMachineModal()">Cancel</button>
                        <button type="submit" class="submit-btn">Save Changes</button>
                    </div>
                `;
                
                document.querySelector('#addMachineModal h2').textContent = 'Edit Machine';
                document.getElementById('addMachineModal').style.display = 'block';
                
                // Initialize file upload handlers
                initializeFileUpload();
                
                // Add form submit handler
                form.onsubmit = function(e) {
                    e.preventDefault();
                    const formData = new FormData(this);
                    
                    fetch('dashboard.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('Machine updated successfully!');
                            closeAddMachineModal();
                            window.location.reload();
                        } else {
                            alert('Error: ' + (data.error || 'Update failed'));
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Failed to update machine: ' + error.message);
                    });
                };
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to load machine details: ' + error.message);
            });
        }

        // Add this function to reinitialize file upload handlers
        function initializeFileUpload() {
            const fileInput = document.querySelector('.file-upload input[type="file"]');
            const previewContainer = document.querySelector('.image-preview');
            const fileNameDisplay = document.querySelector('.selected-file-name');
            const previewImage = previewContainer?.querySelector('img');
            const removeButton = document.querySelector('.remove-image');

            // Guard clause to prevent errors if elements don't exist
            if (!fileInput || !previewContainer || !fileNameDisplay) {
                console.warn('Required file upload elements not found');
                return;
            }

            fileInput.addEventListener('change', function() {
                const file = this.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.addEventListener('load', function() {
                        if (previewImage) {
                            previewImage.src = this.result;
                            previewContainer.classList.add('active');
                        }
                    });
                    fileNameDisplay.textContent = file.name;
                    reader.readAsDataURL(file);
                }
            });

            // Only add remove button functionality if it exists
            if (removeButton) {
                removeButton.addEventListener('click', function() {
                    fileInput.value = '';
                    previewContainer.classList.remove('active');
                    fileNameDisplay.textContent = '';
                    if (previewImage) {
                        previewImage.src = '';
                    }
                });
            }
        }
        // Update form submission handler
        document.addEventListener('DOMContentLoaded', function() {
            const addMachineForm = document.getElementById('addMachineForm');
            
            if (addMachineForm) {
                addMachineForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const formData = new FormData(this);
                    formData.append('action', 'add_machine');
                    
                    fetch('dashboard.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('Machine added successfully!');
                            closeAddMachineModal();
                            window.location.reload();
                        } else {
                            alert('Error: ' + (data.error || 'Failed to add machine'));
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Failed to add machine: ' + error.message);
                    });
                });
            }
        });

        function toggleMachineStatus(machineName) {
            const formData = new FormData();
            formData.append('action', 'toggle_status');
            formData.append('machine_name', machineName);
            
            fetch('dashboard.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                }
            });
        }
        function deleteMachine(machineName) {
            if (confirm('Are you sure you want to delete this machine? This action cannot be undone.')) {
                fetch('dashboard.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=delete&machine_name=${encodeURIComponent(machineName)}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Remove the machine card from the UI
                        const machineCard = document.querySelector(`[data-machine="${machineName}"]`);
                        machineCard.remove();
                        
                        // Show success message
                        alert('Machine successfully deleted');
                    }
                });
            }
        }

        function updateMachineStatus() {
            fetch('dashboard.php?action=get_status')
                .then(response => response.json())
                .then(data => {
                    data.forEach(machine => {
                        const machineCard = document.querySelector(`[data-machine="${machine.machine_name}"]`);
                        if (machineCard) {
                            const statusElement = machineCard.querySelector('.machine-status');
                            statusElement.textContent = machine.status;
                            statusElement.className = `machine-status ${
                                machine.status === 'ว่าง' ? 'status-available' : 'status-in-use'
                            }`;
                        }
                    });
                });
        }

        function showAddMachineModal() {
            document.getElementById('addMachineModal').style.display = 'block';
        }

        function closeAddMachineModal() {
            document.getElementById('addMachineModal').style.display = 'none';
        }

        // Add this after your existing admin functions
        document.addEventListener('DOMContentLoaded', function() {
            const fileInput = document.querySelector('.file-upload input[type="file"]');
            const previewContainer = document.querySelector('.image-preview');
            const previewImage = previewContainer.querySelector('img');
            const fileNameDisplay = document.querySelector('.selected-file-name');
            
            if (fileInput) {
                fileInput.addEventListener('change', function() {
                    const file = this.files[0];
                    
                    if (file) {
                        const reader = new FileReader();
                        
                        reader.addEventListener('load', function() {
                            previewImage.src = this.result;
                            previewContainer.classList.add('active');
                        });
                        
                        fileNameDisplay.textContent = file.name;
                        reader.readAsDataURL(file);
                    } else {
                        previewContainer.classList.remove('active');
                        fileNameDisplay.textContent = '';
                    }
                });

                // Drag and drop functionality
                const uploadArea = document.querySelector('.file-upload');
                
                ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                    uploadArea.addEventListener(eventName, preventDefaults, false);
                });

                function preventDefaults(e) {
                    e.preventDefault();
                    e.stopPropagation();
                }

                ['dragenter', 'dragover'].forEach(eventName => {
                    uploadArea.addEventListener(eventName, highlight, false);
                });

                ['dragleave', 'drop'].forEach(eventName => {
                    uploadArea.addEventListener(eventName, unhighlight, false);
                });

                function highlight() {
                    uploadArea.classList.add('highlight');
                }

                function unhighlight() {
                    uploadArea.classList.remove('highlight');
                }

                uploadArea.addEventListener('drop', handleDrop, false);

                function handleDrop(e) {
                    const dt = e.dataTransfer;
                    const file = dt.files[0];
                    fileInput.files = dt.files;
                    
                    if (file) {
                        const reader = new FileReader();
                        
                        reader.addEventListener('load', function() {
                            previewImage.src = this.result;
                            previewContainer.classList.add('active');
                        });
                        
                        fileNameDisplay.textContent = file.name;
                        reader.readAsDataURL(file);
                    }
                }
            }
        });
        const removeButton = document.querySelector('.remove-image');
        removeButton.addEventListener('click', function() {
            fileInput.value = '';
            previewContainer.classList.remove('active');
            fileNameDisplay.textContent = '';
        });


    <?php endif; ?>

    // User Functions
    <?php if (!$isAdmin): ?>
        // Filter functionality
        document.querySelectorAll('.filter-button').forEach(button => {
            button.addEventListener('click', function() {
                document.querySelectorAll('.filter-button').forEach(btn => 
                    btn.classList.remove('active')
                );
                this.classList.add('active');
                filterMachines(this.dataset.filter);
            });
        });

        function filterMachines(status) {
            const machineCards = document.querySelectorAll('.machine-card, .machine');

            machineCards.forEach(card => {
                const machineStatusElement = card.querySelector('.machine-status, .status');
                if (!machineStatusElement) return;
                
                const machineStatus = machineStatusElement.textContent.trim();

                if (status === 'all' || 
                    (status === 'เครื่องว่าง' && machineStatus.includes('ว่าง')) ||
                    (status === 'กำลังใช้งาน' && machineStatus.includes('กำลังใช้งาน'))) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });

            document.querySelectorAll('.filter-button').forEach(btn => 
                btn.classList.remove('active')
            );
            document.querySelector(`[data-filter="${status}"]`).classList.add('active');
        }

        // Real-time search as user types
        const searchInput = document.querySelector('.search-input');
            searchInput.addEventListener('input', () => {
                searchMachines();
        });

            // Keep existing button click
        const searchButton = document.querySelector('.search-section .filter-button');
            searchButton.addEventListener('click', () => {
                searchMachines();
        });
        
        // Search button click
        searchButton.addEventListener('click', () => {
            searchMachines();
        });

        // Enter key press
        searchInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                searchMachines();
            }
        });

        function searchMachines() {
            const searchInput = document.querySelector('.search-input').value.trim();
            const machineCards = document.querySelectorAll('.machine-card');

            machineCards.forEach(card => {
                const capacityText = card.querySelector('p:nth-child(4)').textContent;
                const capacityNumber = capacityText.match(/\d+/)[0];
                
                if (searchInput === '') {
                    card.style.display = 'block';
                } else {
                    card.style.display = capacityNumber.includes(searchInput) ? 'block' : 'none';
                }
            });
        }

        function showBookingModal(machineName) {
            fetch(`get_machine_details.php?machine=${machineName}`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('bookingDetails').innerHTML = `
                        <h2>Booking Details</h2>
                        <div class="machine-info">
                            <img src="${data.machine_images}" alt="${data.machine_name}">
                            <div class="details">
                                <p>Machine: ${data.machine_name}</p>
                                <p>Capacity: ${data.capacity}kg</p>
                                <p>Price: ${data.price_per_use} บาท</p>
                                <p>Washing Time: ${data.washing_time} minutes</p>
                            </div>
                        </div>
                        <button onclick="confirmBooking('${data.machine_name}')">ชำระเงิน</button>
                    `;
                    document.getElementById('bookingModal').style.display = 'block';
                });
        }

        function closeBookingModal() {
            document.getElementById('bookingModal').style.display = 'none';
        }

        function confirmBooking(machineName) {
            window.location.href = `booking.php?machine=${machineName}`;
        }
    <?php endif; ?>

    // Common Functions
    function updateMachineStatus() {
        fetch('machine_status.php')
            .then(response => response.json())
            .then(data => {
                data.forEach(machine => {
                    const machineCard = document.querySelector(`[data-machine="${machine.machine_name}"]`);
                    if (machineCard) {
                        const statusElement = machineCard.querySelector('.machine-status');
                        statusElement.textContent = machine.status;
                        statusElement.className = `machine-status ${
                            machine.status === 'เครื่องว่าง' ? 'status-available' : 'status-in-use'
                        }`;
                    }
                });
            });
    }

    // Initialize
    document.addEventListener('DOMContentLoaded', function() {
        updateMachineStatus();
        setInterval(updateMachineStatus, 30000); // Update every 30 seconds
    });

    // Close modals when clicking outside
    window.onclick = function(event) {
        if (event.target.classList.contains('modal')) {
            event.target.style.display = 'none';
        }
    }

</script>

</body>
</html>