<?php
session_start();
require_once("connectDB.php"); // ใช้ PDO

if (!isset($pdo)) {
    die("Database connection not established.");
}

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit();
}

// Create profiles directory if it doesn't exist
$profilesDir = 'assets/images/profiles/';
if (!file_exists($profilesDir)) {
    mkdir($profilesDir, 0777, true);
    chmod($profilesDir, 0777);
}

$username = $_SESSION['username'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
$stmt->execute([$username]);
$user = $stmt->fetch();

// Fetch user's booking history
$historyStmt = $pdo->prepare("SELECT 
    b.*, 
    w.machine_name,
    w.machine_images,
    m.price_per_use
FROM bookings b
JOIN washing_machines w ON b.machine_name = w.machine_name
JOIN machine_types m ON w.serial_number = m.serial_number
WHERE b.username = ?
ORDER BY b.booking_date DESC");
$historyStmt->execute([$username]);
$bookings = $historyStmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_username = $_POST['new_username'] ?? $username;
    $new_password = $_POST['new_password'] ?? null;
    $confirm_password = $_POST['confirm_password'] ?? null;
    $profile_picture = $_FILES['profile_picture'] ?? null;
    
    $pdo->beginTransaction();
    try {
        // Handle password update
        if ($new_password) {
            if ($new_password !== $confirm_password) {
                throw new Exception("Passwords do not match!");
            }
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE username = ?");
            $stmt->execute([$hashed_password, $username]);
        }
        
        // Handle profile picture upload
        if ($profile_picture && $profile_picture['error'] === 0) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            if (!in_array($profile_picture['type'], $allowed_types)) {
                throw new Exception("Invalid file type. Please upload JPG, PNG or GIF.");
            }
            
            $file_extension = strtolower(pathinfo($profile_picture['name'], PATHINFO_EXTENSION));
            $new_filename = time() . '_' . $username . '.' . $file_extension;
            $upload_path = $profilesDir . $new_filename;
            
            if (move_uploaded_file($profile_picture['tmp_name'], $upload_path)) {
                // Delete old profile picture if exists
                if ($user['profile_picture'] && file_exists($profilesDir . $user['profile_picture'])) {
                    unlink($profilesDir . $user['profile_picture']);
                }
                
                $stmt = $pdo->prepare("UPDATE users SET profile_picture = ? WHERE username = ?");
                $stmt->execute([$new_filename, $username]);
                
                // Log successful upload
                error_log("Profile picture updated successfully: " . $new_filename);
            } else {
                throw new Exception("Failed to upload profile picture.");
            }
        }
        
        // Handle username update
        if ($new_username !== $username) {
            $stmt = $pdo->prepare("UPDATE users SET username = ? WHERE username = ?");
            $stmt->execute([$new_username, $username]);
            $_SESSION['username'] = $new_username;
        }
        
        $pdo->commit();
        $success = "Profile updated successfully!";
        
        // Refresh the page to show updates
        header("Location: profile.php");
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Update failed: " . $e->getMessage();
        error_log("Profile update error: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - Jamoon Laundry</title>
    <link rel="stylesheet" href="style2.css">
</head>
<body>
    <?php if ($_SESSION['username'] === 'admin@jamoon'): ?>
        <!-- Admin Header -->
        <header class="header">
            <div class="logo">
                <img src="logo.png" alt="Jamoon Laundry">
            </div>
            <nav class="nav-menu">
                <a href="dashboard.php">Dashboard</a>
                <a href="admin.php#machines">Machines</a>
                <a href="admin.php#users">Users</a>
                <a href="admin.php#reports">Reports</a>
            </nav>
            <div class="profile-section">
                <div class="profile-image">
                    <img src="<?php echo $user['profile_picture'] ? 'assets/images/profiles/' . $user['profile_picture'] : 'user.png'; ?>" alt="Profile">
                </div>
                <div class="profile-info">
                    <span class="username"><?php echo $_SESSION['username']; ?></span>
                    <a href="index.php?logout=true" class="logout-link">Logout</a>
                </div>
            </div>
        </header>
    <?php else: ?>
        <!-- User Header -->
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
                <div class="profile-image">
                    <img src="<?php echo $user['profile_picture'] ? 'assets/images/profiles/' . $user['profile_picture'] : 'user.png'; ?>" alt="Profile">
                </div>
                <div class="profile-info">
                    <span class="username"><?php echo $_SESSION['username']; ?></span>
                    <a href="index.php?logout=true" class="logout-link">Logout</a>
                </div>
            </div>
        </header>
    <?php endif; ?>


    <div class="profile-container">
        <?php if (isset($success)): ?>
            <div class="message success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="message error"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="profile-header">
            <div class="profile-image-container">
                <img 
                    src="<?php echo $user['profile_picture'] ? 'assets/images/profiles/' . $user['profile_picture'] : 'user.png'; ?>" 
                    alt="Profile Picture"
                    class="profile-picture"
                    id="preview-image"
                >
                <div class="image-overlay">
                    <span>Change Photo</span>
                </div>
            </div>
            <div class="profile-info">
                <h1><?php echo htmlspecialchars($username); ?></h1>
                <p>Member since: <?php echo date('F j, Y', strtotime($user['created_at'])); ?></p>
            </div>
        </div>


        <form method="POST" action="" enctype="multipart/form-data" class="profile-form">
            <!-- Hidden file input for profile picture -->
            <input 
                type="file" 
                id="profile_picture" 
                name="profile_picture" 
                accept="image/*" 
                style="display: none;"
                onchange="previewImage(this)"
            >
            
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="new_username" value="<?php echo htmlspecialchars($username); ?>">
            </div>

            <div class="form-group">
                <label>New Password (leave blank to keep current)</label>
                <input type="password" name="new_password" id="new_password">
            </div>

            <div class="form-group">
                <label>Confirm New Password</label>
                <input type="password" name="confirm_password" id="confirm_password">
            </div>

            <div class="action-buttons">
                <button type="submit" class="save-button">Save Changes</button>
                <a href="dashboard.php" class="cancel-button">Cancel</a>
            </div>
        </form>
        
        <?php if (!isset($_SESSION['username']) || $_SESSION['username'] !== 'admin@jamoon'): ?>
            <div class="history-section">
                <h2>Recent Bookings</h2>
                <div class="history-list">
                    <?php foreach($bookings as $booking): ?>
                        <div class="history-item">
                            <img src="<?php echo $booking['machine_images']; ?>" alt="Machine">
                            <div class="booking-details">
                                <h3><?php echo $booking['machine_name']; ?></h3>
                                <p>Booking Date: <?php echo date('F j, Y, g:i a', strtotime($booking['booking_date'])); ?></p>
                                <p>Status: <?php echo $booking['usage_status']; ?></p>
                                <p>Price: <?php echo $booking['price_per_use']; ?> บาท</p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

<script>
 // Trigger file input when clicking profile image container
document.querySelector('.profile-image-container').addEventListener('click', function() {
    document.getElementById('profile_picture').click();
});

// Handle file selection and preview
document.getElementById('profile_picture').addEventListener('change', function(e) {
    if (e.target.files && e.target.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('preview-image').src = e.target.result;
            document.querySelector('.profile-picture').src = e.target.result;
        }
        reader.readAsDataURL(e.target.files[0]);
    }
});

// Password validation
document.querySelector('.profile-form').addEventListener('submit', function(e) {
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = document.getElementById('confirm_password').value;

    if (newPassword && newPassword !== confirmPassword) {
        e.preventDefault();
        alert('Passwords do not match!');
    }
});

</script>
</body>
</html>
