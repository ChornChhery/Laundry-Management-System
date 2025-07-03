<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jamoon Laundry</title>
    <link rel = "icon" href="logo.png">
</head>

<style>
    body {
        margin: 0;
        font-family: 'Poppins', sans-serif;
        background-color: #f5f5f5;
    }

    header {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 120px; /* ปรับความสูงตามต้องการ */
    background: linear-gradient(to right, #141E30, #243B55);
    color: white;
    text-align: center;
    line-height: 80px; /* จัดให้ข้อความอยู่ตรงกลาง */
    box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.3);
    z-index: 1000; /* ให้แทบอยู่บนสุด */
}

header h2 {
    font-size: 32px; /* ขยายตัวอักษรให้ใหญ่ขึ้น */
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 2px;
    position: relative;
    z-index: 2;
    animation: fadeIn 1s ease-in-out;
      
    /* ขยับขึ้นด้านบน */
    margin-top: 15px; /* ปรับค่าตามต้องการ */
}

/* เส้นใต้อักษรแบบแอนิเมชัน */
header h2::after {
    content: "";
    display: block;
    width: 60px;
    height: 4px;
    background: white;
    margin: 10px auto 0;
    border-radius: 2px;
    transition: width 0.3s ease-in-out;
}

header:hover h2::after {
    width: 120px;
}

/* เอฟเฟกต์แสงแฟลช */
header::before {
    content: "";
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: rgba(255, 255, 255, 0.2);
    transform: skewX(-30deg);
    transition: left 0.5s ease-in-out;
}

header:hover::before {
    left: 100%;
}

/* แอนิเมชัน Fade In */
@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

    .hero {
        position: relative;
        height: 100vh;
        display: flex;
        justify-content: center;
        align-items: center;
        text-align: center;
        color: white;
    }

    .background-video {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        object-fit: cover;
        z-index: -1;
        filter: brightness(0.8);
    }

    .content {
        padding: 50px 70px;
        animation: fadeInUp 1s ease-out;
        margin-top: 50px; 
    }

    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(50px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .content h1 {
        font-size: 42px;
        font-weight: bold;
        color: #00416A;
        margin-bottom: 20px;
        text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.2);
    }

    .content h1 span {
        background: linear-gradient(45deg, #00416A, #4CA1AF);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .content p {
        font-size: 22px;
        color: #fff;
        margin-bottom: 30px;
        font-weight: 1000;
        margin-top: 500px;
    }

    .cta {
        padding: 15px 35px;
        font-size: 20px;
        font-weight: bold;
        text-transform: uppercase;
        color: white;
        background: linear-gradient(45deg, #00416A, #4CA1AF);
        border: none;
        border-radius: 50px;
        cursor: pointer;
        transition: all 0.4s ease;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
    }

    .cta:hover {
        transform: translateY(-3px) scale(1.05);
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3);
        background: linear-gradient(45deg, #4CA1AF, #00416A);
    }

    .cta:active {
        transform: translateY(100px);
    }

    .cta-button {
        text-decoration: none;
        display: inline-block;
    }


    /* Responsive Design */
    @media (max-width: 768px) {
        header {
            height: 80px;
            line-height: 60px;
        }

        header h2 {
            font-size: 24px;
        }

        .hero {
            height: 80vh;
        }

        .content p {
            font-size: 18px;
            margin-top: 200px;
        }

        .cta {
            font-size: 16px;
            padding: 10px 25px;
        }
    }

    @media (max-width: 480px) {
        header h2 {
            font-size: 20px;
        }

        .content h1 {
            font-size: 28px;
        }

        .content p {
            font-size: 16px;
        }

        .cta {
            font-size: 14px;
            padding: 8px 20px;
        }
    }
    
</style>

<body>
    <header>
        <h2>✨ Jamoon Laundry ! ✨</h2>
    </header>

    <section class="hero">
        <video autoplay loop class="background-video">
            <source src="preview.mp4" type="video/mp4">
        </video>
        
        <div class="content">
            <p>ซักสะอาด สะดวก รวดเร็ว 24 ชั่วโมง</p>
            <a href="login.php" class="cta-button">
                <button class="cta">คลิกเข้าสู่ระบบ</button>
            </a>
        </div>
    </section>

    <script>
        // Enable video sound when user interacts
        document.addEventListener('click', function() {
            const video = document.querySelector('video');
            video.muted = false;
        });

        document.addEventListener('DOMContentLoaded', function() {
        const video = document.querySelector('video');
        video.play();
        
        // ตรวจสอบถ้าวิดีโอหยุด
        video.addEventListener('pause', function() {
            video.play();
        });
        
        // รีสตาร์ทวิดีโอเมื่อเล่นจบ
        video.addEventListener('ended', function() {
            video.currentTime = 0;
            video.play();
        });
    });
    </script>
</body>
</html>
