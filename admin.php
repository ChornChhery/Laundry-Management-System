<?php
session_start();
require_once("connectDB.php"); // ใช้ PDO

if (!isset($pdo)) {
    die("Database connection not established.");
}

if (!isset($_SESSION['username']) || $_SESSION['username'] !== 'admin@jamoon') {
    header("Location: index.php");
    exit();
}
// Handle AJAX request for report data
if (isset($_POST['action']) && $_POST['action'] === 'get_report') {
    $query = $_POST['query'];
    $result = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);
    header('Content-Type: application/json');
    echo json_encode($result);
    exit;
}

// Machine Statistics with Daily Usage
$machineStats = $pdo->query("
    SELECT 
        w.machine_name,
        w.status,
        w.machine_images,
        w.serial_number,
        mt.capacity,
        mt.price_per_use,
        mt.washing_time,
        COUNT(b.booking_reference) as total_bookings,
        SUM(CASE WHEN b.usage_status = 'เสร็จสิ้น' THEN mt.price_per_use ELSE 0 END) as total_revenue,
        COUNT(CASE WHEN DATE(b.booking_date) = CURDATE() THEN 1 END) as today_usage
    FROM washing_machines w
    JOIN machine_types mt ON w.serial_number = mt.serial_number
    LEFT JOIN bookings b ON w.machine_name = b.machine_name
    GROUP BY w.machine_name, w.status, mt.capacity, mt.price_per_use
    ORDER BY total_bookings DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Daily Machine Usage for Charts
$dailyUsageStats = $pdo->query("
    SELECT 
        DATE(b.booking_date) as date,
        GROUP_CONCAT(DISTINCT w.machine_name ORDER BY w.machine_name) as machines_used,
        COUNT(DISTINCT b.booking_reference) as usage_count,
        SUM(mt.price_per_use) as revenue
    FROM bookings b
    JOIN washing_machines w ON b.machine_name = w.machine_name
    JOIN machine_types mt ON w.serial_number = mt.serial_number
    GROUP BY DATE(b.booking_date)
    ORDER BY date DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Monthly Revenue per Machine
$monthlyRevenue = $pdo->query("
    SELECT 
        w.machine_name,
        DATE_FORMAT(b.booking_date, '%Y-%m') as month,
        COUNT(*) as total_uses,
        SUM(CASE WHEN b.usage_status = 'เสร็จสิ้น' THEN mt.price_per_use ELSE 0 END) as revenue
    FROM washing_machines w
    LEFT JOIN bookings b ON w.machine_name = b.machine_name
    LEFT JOIN machine_types mt ON w.serial_number = mt.serial_number
    WHERE b.booking_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY w.machine_name, DATE_FORMAT(b.booking_date, '%Y-%m')
    ORDER BY month, w.machine_name
")->fetchAll(PDO::FETCH_ASSOC);

// User Statistics with Favorite Machine and Last Booking Date
$userStats = $pdo->query("
    SELECT 
        u.username,
        u.created_at,
        COUNT(b.booking_reference) as total_bookings,
        SUM(CASE WHEN b.usage_status = 'เสร็จสิ้น' THEN mt.price_per_use ELSE 0 END) as total_spent,
        (
            SELECT machine_name
            FROM bookings b2
            WHERE b2.username = u.username
            GROUP BY machine_name
            ORDER BY COUNT(*) DESC
            LIMIT 1
        ) as favorite_machine,
        MAX(b.booking_date) as last_booking_date
    FROM users u
    LEFT JOIN bookings b ON u.username = b.username
    LEFT JOIN washing_machines w ON b.machine_name = w.machine_name
    LEFT JOIN machine_types mt ON w.serial_number = mt.serial_number
    WHERE u.role = 'user'
    GROUP BY u.username, u.created_at
")->fetchAll(PDO::FETCH_ASSOC);


// Overall Statistics with Today's Data
$overallStats = $pdo->query("
    SELECT 
        COUNT(DISTINCT CASE WHEN u.role = 'user' THEN u.username END) as total_users,
        COUNT(b.booking_reference) as total_bookings,
        SUM(CASE WHEN b.usage_status = 'เสร็จสิ้น' THEN mt.price_per_use ELSE 0 END) as total_revenue,
        COUNT(CASE WHEN DATE(b.booking_date) = CURDATE() THEN 1 END) as today_bookings,
        SUM(CASE WHEN DATE(b.booking_date) = CURDATE() AND b.usage_status = 'เสร็จสิ้น' 
            THEN mt.price_per_use ELSE 0 END) as today_revenue
    FROM users u
    LEFT JOIN bookings b ON u.username = b.username
    LEFT JOIN washing_machines w ON b.machine_name = w.machine_name
    LEFT JOIN machine_types mt ON w.serial_number = mt.serial_number
")->fetch(PDO::FETCH_ASSOC);

// Prepare data for charts
$chartLabels = [];
$chartData = [];

if (!empty($dailyUsageStats)) {
    $chartLabels = array_unique(array_column($dailyUsageStats, 'date'));
    $machineNames = array_unique(array_column($machineStats, 'machine_name'));

    foreach ($machineNames as $machine) {
        $usageData = [];
        foreach ($chartLabels as $date) {
            $usage = 0;
            foreach ($dailyUsageStats as $stat) {
                if (isset($stat['machine_name']) && $stat['machine_name'] === $machine && $stat['date'] === $date) {
                    $usage = $stat['usage_count'];
                    break;
                }
            }
            $usageData[] = $usage;
        }
        $chartData[$machine] = $usageData;
    }
}

// Get user profile data
$username = $_SESSION['username'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
$stmt->execute([$username]);
$user = $stmt->fetch();

//Edit and Delete Machine
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'delete':
            $stmt = $pdo->prepare("DELETE FROM washing_machines WHERE machine_name = ?");
            $success = $stmt->execute([$_POST['machine_name']]);
            echo json_encode(['success' => $success]);
            exit;
            
        case 'edit':
            $stmt = $pdo->prepare("UPDATE washing_machines SET 
                serial_number = ?,
                machine_images = ?
                WHERE machine_name = ?");
            $success = $stmt->execute([
                $_POST['serial_number'],
                $_POST['machine_images'],
                $_POST['machine_name']
            ]);
            
            if ($success) {
                $stmt = $pdo->prepare("UPDATE machine_types SET 
                    capacity = ?,
                    price_per_use = ?,
                    washing_time = ?
                    WHERE serial_number = ?");
                $success = $stmt->execute([
                    $_POST['capacity'],
                    $_POST['price_per_use'],
                    $_POST['washing_time'],
                    $_POST['serial_number']
                ]);
            }
            
            echo json_encode(['success' => $success]);
            exit;
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Jamoon Laundry</title>
    <link rel="icon" href="logo.png">
    <link rel="stylesheet" href="style3.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

</head>
<body>
    <div class="admin-details">
        <header class="header">
            <div class="logo">
                <img src="logo.png" alt="Jamoon Laundry">
            </div>
            <nav class="nav-menu">
                <a href="dashboard.php">Home</a>
                <a href="#machines">Machines</a>
                <a href="#users">Users</a>
                <a href="#reports">Reports</a>
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

        <section id="machines" class="detail-section">
            <h2>Machine Statistics</h2>

            <div class="stats-grid">
                <?php foreach ($machineStats as $machine): ?>
                <div class="stat-card">
                    <img src="<?php echo $machine['machine_images']; ?>" alt="<?php echo $machine['machine_name']; ?>" class="machine-image">
                    <h3><?php echo $machine['machine_name']; ?></h3>
                    <p>Capacity: <?php echo $machine['capacity']; ?> kg</p>
                    <p>Price: ฿<?php echo $machine['price_per_use']; ?></p>
                    <p>Washing Time: <?php echo $machine['washing_time']; ?> minutes</p>
                    <p>Total Bookings: <?php echo $machine['total_bookings']; ?></p>
                    <p>Revenue: ฿<?php echo number_format($machine['total_revenue']); ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section id="users" class="detail-section">
            <h2>User Management</h2>
            <div class="user-filters">
                <input type="text" id="userSearch" placeholder="Search users...">
                <select id="sortBy">
                    <option value="bookings">Sort by Bookings</option>
                    <option value="spent">Sort by Spent</option>
                    <option value="date">Sort by Join Date</option>
                </select>
            </div>
            <div class="user-table">
                <table>
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Join Date</th>
                            <th>Total Bookings</th>
                            <th>Total Spent</th>
                            <th>Favorite Machine</th>
                            <th>Last Booking</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($userStats as $user): ?>
                        <tr>
                            <td><?php echo $user['username']; ?></td>
                            <td><?php echo date('Y-m-d', strtotime($user['created_at'])); ?></td>
                            <td><?php echo $user['total_bookings']; ?></td>
                            <td>฿<?php echo number_format($user['total_spent']); ?></td>
                            <td><?php echo $user['favorite_machine']; ?></td>
                            <td><?php echo $user['last_booking_date'] ? date('Y-m-d', strtotime($user['last_booking_date'])) : 'Never'; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="chart-container">
                <canvas id="userActivityChart"></canvas>
            </div>
        </section>

        <section id="reports" class="detail-section">
            <h2>Overall Reports</h2>
            <div class="report-summary">
                <div class="summary-card">
                    <h3>Total Users</h3>
                    <p><?php echo $overallStats['total_users']; ?></p>
                </div>
                <div class="summary-card">
                    <h3>Total Revenue</h3>
                    <p>฿<?php echo number_format($overallStats['total_revenue']); ?></p>
                </div>
                <div class="summary-card">
                    <h3>Total Bookings</h3>
                    <p><?php echo $overallStats['total_bookings']; ?></p>
                </div>
                <div class="summary-card">
                    <h3>Today's Bookings</h3>
                    <p><?php echo $overallStats['today_bookings']; ?></p>
                </div>
            </div>
            
            <div class="detailed-reports">
                <div class="chart-container">
                    <h3>Report Graph</h3>
                    <canvas id="machineComparisonChart"></canvas>
                </div>
                
                <div class="monthly-summary">
                    <h3>Monthly Summary</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Month</th>
                                <th>Total Bookings</th>
                                <th>Total Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $monthSummary = [];
                            foreach ($monthlyRevenue as $revenue) {
                                if (!isset($monthSummary[$revenue['month']])) {
                                    $monthSummary[$revenue['month']] = [
                                        'total_uses' => 0,
                                        'revenue' => 0
                                    ];
                                }
                                $monthSummary[$revenue['month']]['total_uses'] += $revenue['total_uses'];
                                $monthSummary[$revenue['month']]['revenue'] += $revenue['revenue'];
                            }
                            
                            foreach ($monthSummary as $month => $stats): ?>
                            <tr>
                                <td><?php echo $month; ?></td>
                                <td><?php echo $stats['total_uses']; ?></td>
                                <td>฿<?php echo number_format($stats['revenue']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="usage-table">
                    <h3>Detailed Usage Report</h3>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Machines Used</th>
                                    <th>Total Bookings</th>
                                    <th>Total Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($dailyUsageStats)): ?>
                                    <tr>
                                        <td colspan="4">No bookings found</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($dailyUsageStats as $stat): ?>
                                    <tr>
                                        <td><?php echo $stat['date']; ?></td>
                                        <td><?php echo $stat['machines_used']; ?></td>
                                        <td><?php echo $stat['usage_count']; ?></td>
                                        <td>฿<?php echo number_format($stat['revenue']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>

        
<script>
    
document.addEventListener('DOMContentLoaded', function() {
    // Navigation Control
    const sections = document.querySelectorAll('.detail-section');
    const navLinks = document.querySelectorAll('.nav-menu a');

    function showSection(sectionId) {
        sections.forEach(section => {
            section.style.display = 'none';
        });
        document.getElementById(sectionId).style.display = 'block';
    }

    navLinks.forEach(link => {
        link.addEventListener('click', (e) => {
            const sectionId = link.getAttribute('href').substring(1);
            showSection(sectionId);
        });
    });

    // Show initial section based on URL hash or default to machines
    const initialSection = window.location.hash.substring(1) || 'machines';
    showSection(initialSection);

    // User search functionality with real-time filtering
    document.getElementById('userSearch').addEventListener('input', function() {
        const searchInput = this.value.toLowerCase().trim();
        const tableRows = document.querySelectorAll('.user-table tbody tr');
        
        tableRows.forEach(row => {
            const rowText = Array.from(row.cells)
                .map(cell => cell.textContent.toLowerCase())
                .join(' ');
            
            row.style.display = rowText.includes(searchInput) ? '' : 'none';
        });
    });    

    // User sorting functionality
    document.getElementById('sortBy').addEventListener('change', function() {
        const sortValue = this.value;
        const tbody = document.querySelector('.user-table tbody');
        const rows = Array.from(tbody.querySelectorAll('tr'));

        rows.sort((a, b) => {
            let aValue, bValue;
            
            switch(sortValue) {
                case 'bookings':
                    aValue = parseInt(a.children[2].textContent);
                    bValue = parseInt(b.children[2].textContent);
                    break;
                case 'spent':
                    aValue = parseInt(a.children[3].textContent.replace('฿', '').replace(',', ''));
                    bValue = parseInt(b.children[3].textContent.replace('฿', '').replace(',', ''));
                    break;
                case 'date':
                    aValue = new Date(a.children[1].textContent);
                    bValue = new Date(b.children[1].textContent);
                    break;
            }
            return bValue - aValue;
        });

        tbody.innerHTML = '';
        rows.forEach(row => tbody.appendChild(row));
    });

    // User Activity Chart
    const userCtx = document.getElementById('userActivityChart').getContext('2d');
        new Chart(userCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($userStats, 'username')); ?>,
                datasets: [{
                    label: 'Total Bookings',
                    data: <?php echo json_encode(array_column($userStats, 'total_bookings')); ?>,
                    backgroundColor: 'rgba(54, 162, 235, 0.5)'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                        text: 'User Booking Activity',
                        font: { size: 16 }
                    },
                    legend: {
                        position: 'bottom'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    
    // Machine Performance Chart
    const machineComparisonCtx = document.getElementById('machineComparisonChart').getContext('2d');
        new Chart(machineComparisonCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($machineStats, 'machine_name')); ?>,
                datasets: [
                    {
                        label: 'Total Bookings',
                        data: <?php echo json_encode(array_column($machineStats, 'total_bookings')); ?>,
                        backgroundColor: 'rgba(54, 162, 235, 0.7)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 1,
                        yAxisID: 'bookings'
                    },
                    {
                        label: 'Revenue (฿)',
                        data: <?php echo json_encode(array_column($machineStats, 'total_revenue')); ?>,
                        backgroundColor: 'rgba(75, 192, 192, 0.7)',
                        borderColor: 'rgba(75, 192, 192, 1)',
                        borderWidth: 1,
                        yAxisID: 'revenue'
                    }
                ]
            },
            options: {
                responsive: true,
                plugins: {
                    title: {
                        display: true,
                        text: 'Machine Statistic Report',
                        font: { size: 16 }
                    },
                    legend: {
                        position: 'bottom'
                    }
                },
                scales: {
                    bookings: {
                        type: 'linear',
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Number of Bookings'
                        }
                    },
                    revenue: {
                        type: 'linear',
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Revenue (฿)'
                        }
                    }
                }
            }
        });

         // Real-time Updates
        function updateUsageTable() {
            fetch('admin.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_usage_stats'
            })
            .then(response => response.json())
            .then(data => {
                const tableBody = document.querySelector('.usage-table tbody');
                if (data.length === 0) {
                    tableBody.innerHTML = '<tr><td colspan="4">No bookings found</td></tr>';
                    return;
                }
                
                tableBody.innerHTML = data.map(row => `
                    <tr>
                        <td>${row.date}</td>
                        <td>${row.machines_used || 'No machines used'}</td>
                        <td>${row.usage_count}</td>
                        <td>฿${parseInt(row.revenue).toLocaleString()}</td>
                    </tr>
                `).join('');
            });
        }

    // Initialize real-time updates
    updateUsageTable();
    setInterval(updateUsageTable, 300000); // Update every 5 minutes

    const machineCtx = document.getElementById('machineUsageChart').getContext('2d');
    new Chart(machineCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($chartLabels); ?>,
            datasets: <?php echo json_encode($chartData); ?>
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });

    const revenueCtx = document.getElementById('revenueChart').getContext('2d');
    new Chart(revenueCtx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode(array_column($monthlyRevenue, 'month')); ?>,
            datasets: [{
                label: 'Monthly Revenue',
                data: <?php echo json_encode(array_column($monthlyRevenue, 'revenue')); ?>,
                backgroundColor: 'rgba(75, 192, 192, 0.5)'
            }]
        }
    });

    // Trigger initial report load
    document.getElementById('reportPeriod').dispatchEvent(new Event('change'));

    function updateMachineStats() {
        fetch('admin.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=get_report&query=' + encodeURIComponent(`
                SELECT 
                    w.machine_name,
                    w.status,
                    w.machine_images,
                    w.serial_number,
                    mt.capacity,
                    mt.price_per_use,
                    mt.washing_time,
                    COUNT(b.booking_reference) as total_bookings,
                    SUM(CASE WHEN b.usage_status = 'เสร็จสิ้น' THEN mt.price_per_use ELSE 0 END) as total_revenue
                FROM washing_machines w
                JOIN machine_types mt ON w.serial_number = mt.serial_number
                LEFT JOIN bookings b ON w.machine_name = b.machine_name
                GROUP BY w.machine_name, w.status, mt.capacity, mt.price_per_use
                ORDER BY total_bookings DESC
            `)
        })
        .then(response => response.json())
        .then(data => {
            const statsGrid = document.querySelector('.stats-grid');
            statsGrid.innerHTML = data.map(machine => `
                <div class="stat-card">
                    <img src="${machine.machine_images}" alt="${machine.machine_name}" class="machine-image">
                    <h3>${machine.machine_name}</h3>
                    <p>Capacity: ${machine.capacity} kg</p>
                    <p>Price: ฿${machine.price_per_use}</p>
                    <p>Washing Time: ${machine.washing_time} minutes</p>
                    <p>Total Bookings: ${machine.total_bookings}</p>
                    <p>Revenue: ฿${Number(machine.total_revenue).toLocaleString()}</p>
                </div>
            `).join('');
        });
    }
    
    // Initial load
    updateMachineStats();
    
    // Update every 30 seconds
    setInterval(updateMachineStats, 30000);
});

</script>

</body>
</html>
