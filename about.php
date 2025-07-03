<?php
session_start();
require_once("connectDB.php");

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
    <title>About & Contact - Jamoon Laundry</title>
    <link rel="icon" href="logo.png">
    <link rel="stylesheet" href="style3.css">
    <style>
    .content-section {
        display: none; /* Hide all sections by default */
    }

    .content-section.active {
        display: block; /* Show active section */
    }

    .dual-slider {
        display: flex;
        justify-content: space-between;
        gap: 40px;
        padding: 20px;
    }

    .profile-card {
        flex: 1;
        text-align: center;
        background: white;
        border-radius: 15px;
        padding: 30px;
        box-shadow: 0 8px 16px rgba(0,0,0,0.1);
        transition: transform 0.3s ease;
    }

    .profile-card:hover {
        transform: translateY(-10px);
    }

    .creator-image {
        width: 250px;
        height: 250px;
        border-radius: 50%;
        margin-bottom: 20px;
        box-shadow: 0 8px 16px rgba(0,0,0,0.2);
        transition: transform 0.3s ease;
    }

    .profile-card:hover .creator-image {
        transform: scale(1.05);
    }

    .creator-info {
        margin-top: 20px;
    }

    .social-links {
        margin-top: 20px;
        display: flex;
        justify-content: center;
        gap: 20px;
    }

    .social-links a {
        font-size: 24px;
        color: var(--grad-clr1);
        transition: transform 0.3s ease;
    }

    .social-links a:hover {
        transform: scale(1.2);
    }

    /* Common styles for sections */
    .detail-section {
        padding: 80px 20px;
        background: #ffffff;
        margin: 20px 0;
        border-radius: 15px;
        box-shadow: 0 8px 30px rgba(0,0,0,0.1);
    }

    .detail-section h2 {
        text-align: center;
        font-size: 2.5em;
        color: #2c3e50;
        margin-bottom: 40px;
        position: relative;
        animation: slideDown 0.8s ease-out;
    }

    .detail-section h2:after {
        content: '';
        position: absolute;
        bottom: -10px;
        left: 50%;
        transform: translateX(-50%);
        width: 100px;
        height: 3px;
        background: #3498db;
    }

    /* Dual slider layout */
    .dual-slider {
        display: flex;
        justify-content: center;
        gap: 40px;
        flex-wrap: wrap;
        padding: 20px;
    }

    /* Profile cards styling */
    .profile-card {
        background: white;
        border-radius: 20px;
        padding: 30px;
        width: 300px;
        text-align: center;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        animation: fadeIn 1s ease-out;
    }

    .profile-card:hover {
        transform: translateY(-10px);
        box-shadow: 0 15px 30px rgba(0,0,0,0.2);
    }

    .creator-image {
        width: 200px;
        height: 200px;
        border-radius: 50%;
        object-fit: cover;
        margin-bottom: 20px;
        border: 5px solid #141E30;
        transition: transform 0.3s ease;
    }

    .creator-image:hover {
        transform: scale(1.05);
    }

    .creator-info {
        margin-top: 20px;
    }

    .creator-info h3 {
        color: #2c3e50;
        font-size: 1.5em;
        margin-bottom: 15px;
    }

    .creator-info p {
        color: #7f8c8d;
        margin: 8px 0;
        font-size: 1.1em;
    }

    /* Social links styling */
    .social-links {
        margin-top: 20px;
        display: flex;
        justify-content: center;
        gap: 15px;
    }

    .social-links a {
        color: #3498db;
        font-size: 24px;
        transition: transform 0.3s ease, color 0.3s ease;
    }

    .social-links a:hover {
        transform: scale(1.2);
        color: #2980b9;
    }

    /* Animations */
    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* Responsive design */
    @media (max-width: 768px) {
        .dual-slider {
            flex-direction: column;
            align-items: center;
        }
        
        .profile-card {
            width: 90%;
            max-width: 300px;
        }
    }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
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

    <section id="about" class="detail-section content-section">
        <h2>About Us</h2>
        <div class="dual-slider">
            <div class="profile-card">
                <img src="jame.JPG" alt="Creator 1" class="creator-image">
                <div class="creator-info">
                    <h3>Mr. Chhery Chorn</h3>
                    <p>6520310203</p>
                    <p>21 Year</p>
                    <p>Full Stack Developer</p>
                    <p>Faculty of Science and Technology</p>
                    <p>Prince of Songkla University, Pattani Campus</p>
                </div>
            </div>
            <div class="profile-card">
                <img src="moon.JPG" alt="Creator 2" class="creator-image">
                <div class="creator-info">
                    <h3>Mrs. Maimoon Dangdo</h3>
                    <p>6520310134</p>
                    <p>21 Year</p>
                    <p>Front end developer</p>
                    <p>Faculty of Science and Technology</p>
                    <p>Prince of Songkla University, Pattani Campus</p>
                </div>
            </div>
        </div>
    </section>

    <section id="contact" class="detail-section content-section">
        <h2>Contact Us</h2>
        <div class="dual-slider">
            <div class="profile-card">
                <img src="jame.JPG" alt="Contact 1" class="creator-image">
                <h3>Mr. Chhery Chorn</h3>
                <div class="social-links">
                    <a href="https://www.facebook.com/chorn.chhery" target="_blank"><i class="fab fa-facebook"></i></a>
                    <a href="https://www.instagram.com/_jame_isme?igsh=MTlwZjJtNXlnYXhocQ%3D%3D" target="_blank"><i class="fab fa-instagram"></i></a>
                    <a href="#" target="_blank"><i class="fab fa-line"></i></a>
                    <a href="mailto:chherychorn@gmail.com"><i class="fas fa-envelope"></i></a>
                </div>
            </div>
            <div class="profile-card">
                <img src="moon.JPG" alt="Contact 2" class="creator-image">
                <h3>Mrs. Maimoon Dangdo</h3>
                <div class="social-links">
                    <a href="https://www.facebook.com/maimoon.dangdo" target="_blank"><i class="fab fa-facebook"></i></a>
                    <a href="https://www.instagram.com/mmoonnn3?igsh=OTllZGtqNWt4ZDVl" target="_blank"><i class="fab fa-instagram"></i></a>
                    <a href="#" target="_blank"><i class="fab fa-line"></i></a>
                    <a href="mailto:maimoon2546@gmail.com"><i class="fas fa-envelope"></i></a>
                </div>
            </div>
        </div>
    </section>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Show About section by default
        document.getElementById('about').classList.add('active');
        
        // Handle navigation
        document.querySelectorAll('.nav-menu a').forEach(link => {
            link.addEventListener('click', function(e) {
                if (this.getAttribute('href').startsWith('#')) {
                    e.preventDefault();
                    
                    // Hide all sections
                    document.querySelectorAll('.content-section').forEach(section => {
                        section.classList.remove('active');
                    });
                    
                    // Show clicked section
                    const targetId = this.getAttribute('href').substring(1);
                    document.getElementById(targetId).classList.add('active');
                }
            });
        });
    });
</script>
</body>
</html>