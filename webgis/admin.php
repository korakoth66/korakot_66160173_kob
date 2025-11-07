<?php
session_start();
require_once 'auth.php';
require_login();

// โหลดการเชื่อมต่อฐานข้อมูลจากไฟล์ db_connect.php
require_once 'api/db_connect.php';

// ตารางที่ต้องใช้งาน
$tables = ['agi64', 'agi65', 'agi66', 'agi67'];

// ตัวอย่างการเชื่อมต่อฐานข้อมูล
$pdo = db_connect(); // เรียกฟังก์ชันจาก db_connect.php

// Handle form actions
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $table = $_POST['table'] ?? '';
    
    if (!in_array($table, $tables)) {
        $message = 'ตารางไม่ถูกต้อง';
        $message_type = 'danger';
    } else {
        try {
            if ($action === 'create') {
                // กำหนด mapping ชื่อฟิลด์ไทย -> อังกฤษสำหรับ parameters
                $field_mapping = [
                    's_id' => 's_id',
                    's_name' => 's_name',
                    'หลักสูตร' => 'curriculum',
                    'คณะ' => 'faculty',
                    'ภาควิชา' => 'department', 
                    'จบจากโรงเรียน' => 'school',
                    'lat' => 'lat',
                    'long' => 'long',
                    'ตำบล' => 'subdistrict',
                    'อำเภอ' => 'district',
                    'จังหวัด' => 'province'
                ];
                
                $columns = [];
                $values = [];
                $params = [];
                
                foreach ($_POST as $key => $value) {
                    if ($key !== 'action' && $key !== 'table') {
                        // ใช้ชื่อคอลัมน์จริงใน DB (ภาษาไทย) สำหรับ INSERT
                        $columns[] = "\"$key\"";
                        // ใช้ชื่อ parameter ภาษาอังกฤษ
                        $eng_key = $field_mapping[$key] ?? $key;
                        $values[] = ":$eng_key";
                        $params[":$eng_key"] = $value;
                    }
                }
                
                if (!empty($columns)) {
                    $sql = "INSERT INTO \"$table\" (" . implode(',', $columns) . ") VALUES (" . implode(',', $values) . ")";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    $message = 'เพิ่มข้อมูลสำเร็จ!';
                    $message_type = 'success';
                }
                
            } elseif ($action === 'update') {
                $student_id = $_POST['student_id'] ?? '';
                if ($student_id) {
                    // กำหนด mapping ชื่อฟิลด์ไทย -> อังกฤษสำหรับ parameters
                    $field_mapping = [
                        's_name' => 's_name',
                        'หลักสูตร' => 'curriculum',
                        'คณะ' => 'faculty',
                        'ภาควิชา' => 'department',
                        'จบจากโรงเรียน' => 'school',
                        'lat' => 'lat',
                        'long' => 'long',
                        'ตำบล' => 'subdistrict',
                        'อำเภอ' => 'district',
                        'จังหวัด' => 'province'
                    ];
                    
                    $updates = [];
                    $params = [':s_id' => $student_id];
                    
                    foreach ($_POST as $key => $value) {
                        if (!in_array($key, ['action', 'table', 'student_id'])) {
                            $eng_key = $field_mapping[$key] ?? $key;
                            $updates[] = "\"$key\" = :$eng_key";
                            $params[":$eng_key"] = $value;
                        }
                    }
                    
                    if (!empty($updates)) {
                        $sql = "UPDATE \"$table\" SET " . implode(', ', $updates) . " WHERE s_id = :s_id";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute($params);
                        $message = 'อัพเดทข้อมูลสำเร็จ!';
                        $message_type = 'success';
                    }
                }
                
            } elseif ($action === 'delete') {
                $student_id = $_POST['student_id'] ?? '';
                if ($student_id) {
                    $sql = "DELETE FROM \"$table\" WHERE s_id = :s_id";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([':s_id' => $student_id]);
                    $message = 'ลบข้อมูลสำเร็จ!';
                    $message_type = 'success';
                }
            }
        } catch (PDOException $e) {
            $message = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
            $message_type = 'danger';
            error_log("Database Error: " . $e->getMessage());
        }
    }
}

// Get current table data
$current_table = $_GET['table'] ?? '';
$is_overview = empty($current_table);

// Fetch data for current table if not overview
if (!$is_overview && in_array($current_table, $tables)) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM \"$current_table\" ORDER BY s_id");
        $stmt->execute();
        $students = $stmt->fetchAll();
    } catch (PDOException $e) {
        $students = [];
        $message = 'ไม่สามารถโหลดข้อมูล: ' . $e->getMessage();
        $message_type = 'danger';
    }
} else {
    $students = [];
}

// สำหรับหน้า overview - ดึงข้อมูลสถิติทั้งหมด
$overview_stats = [];
$table_details = [];

foreach ($tables as $table) {
    try {
        // นับจำนวนนิสิต
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM \"$table\"");
        $stmt->execute();
        $count = $stmt->fetch()['total'];
        $overview_stats[$table] = $count;
        
        // ดึงข้อมูลสำหรับแผนที่ (เฉพาะที่มีพิกัด)
        $stmt = $pdo->prepare("SELECT *, '$table' as table_name FROM \"$table\" WHERE lat IS NOT NULL AND long IS NOT NULL AND lat != 0 AND long != 0");
        $stmt->execute();
        $table_details[$table] = $stmt->fetchAll();
        
    } catch (PDOException $e) {
        $overview_stats[$table] = 0;
        $table_details[$table] = [];
    }
}

// รวมข้อมูลทั้งหมดสำหรับแผนที่
$all_map_data = [];
foreach ($table_details as $table_data) {
    if (is_array($table_data)) {
        $all_map_data = array_merge($all_map_data, $table_data);
    }
}

// ฟังก์ชันช่วยสำหรับการนับข้อมูลที่ไม่ซ้ำ
function countUniqueValues($data, $field) {
    $values = [];
    foreach ($data as $item) {
        if (is_array($item) && isset($item[$field]) && !empty($item[$field])) {
            $values[] = $item[$field];
        }
    }
    return count(array_unique($values));
}

// ดึงข้อมูลสำหรับ Charts
$chart_data = [];
foreach ($tables as $table) {
    try {
        $chart_data[$table] = [
            'province' => [],
            'curriculum' => [],
            'faculty' => [],
            'department' => [],
            'school' => []
        ];
        
        // จังหวัด
        $stmt = $pdo->prepare("SELECT \"จังหวัด\", COUNT(*) as count FROM \"$table\" WHERE \"จังหวัด\" IS NOT NULL AND \"จังหวัด\" != '' GROUP BY \"จังหวัด\" ORDER BY count DESC LIMIT 10");
        $stmt->execute();
        $chart_data[$table]['province'] = $stmt->fetchAll();
        
        // หลักสูตร
        $stmt = $pdo->prepare("SELECT \"หลักสูตร\", COUNT(*) as count FROM \"$table\" WHERE \"หลักสูตร\" IS NOT NULL AND \"หลักสูตร\" != '' GROUP BY \"หลักสูตร\" ORDER BY count DESC LIMIT 10");
        $stmt->execute();
        $chart_data[$table]['curriculum'] = $stmt->fetchAll();
        
        // คณะ
        $stmt = $pdo->prepare("SELECT \"คณะ\", COUNT(*) as count FROM \"$table\" WHERE \"คณะ\" IS NOT NULL AND \"คณะ\" != '' GROUP BY \"คณะ\" ORDER BY count DESC LIMIT 10");
        $stmt->execute();
        $chart_data[$table]['faculty'] = $stmt->fetchAll();
        
        // ภาควิชา
        $stmt = $pdo->prepare("SELECT \"ภาควิชา\", COUNT(*) as count FROM \"$table\" WHERE \"ภาควิชา\" IS NOT NULL AND \"ภาควิชา\" != '' GROUP BY \"ภาควิชา\" ORDER BY count DESC LIMIT 10");
        $stmt->execute();
        $chart_data[$table]['department'] = $stmt->fetchAll();
        
        // โรงเรียน
        $stmt = $pdo->prepare("SELECT \"จบจากโรงเรียน\", COUNT(*) as count FROM \"$table\" WHERE \"จบจากโรงเรียน\" IS NOT NULL AND \"จบจากโรงเรียน\" != '' GROUP BY \"จบจากโรงเรียน\" ORDER BY count DESC LIMIT 10");
        $stmt->execute();
        $chart_data[$table]['school'] = $stmt->fetchAll();
        
    } catch (PDOException $e) {
        // ไม่ต้องทำอะไร ถ้าเกิด error
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบจัดการข้อมูลนิสิตคณะเกษตรศาสตร์</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary: #3a5a40;   /* เขียวเข้มแบบใบสน */
            --secondary: #6b9080; /* เขียวเทาแบบใบยูคาลิปตัส */
            --accent: #a4c3b2;    /* เขียวอ่อนหม่นแบบมอส */
            --light: #f0f7f4;     /* ขาวอมเขียว */
            --dark: #1b2621;      /* เขียวดำแบบร่มไม้ */
        }
        
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #333;
            min-height: 100vh;
            position: relative;
        }
        
        .header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 30px 0;
            margin-bottom: 30px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .header h1 {
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .header p {
            opacity: 0.9;
            font-size: 1.1rem;
        }
        
        .nav-tabs {
            background: white;
            border-radius: 10px;
            padding: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 25px;
        }
        
        .nav-tabs .nav-link {
            color: var(--dark);
            font-weight: 500;
            border: none;
            padding: 12px 20px;
            border-radius: 8px;
            margin: 0 5px;
            transition: all 0.3s;
        }
        
        .nav-tabs .nav-link:hover {
            background-color: rgba(44, 85, 48, 0.1);
        }
        
        .nav-tabs .nav-link.active {
            background-color: var(--primary);
            color: white;
        }
        
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            margin-bottom: 25px;
            transition: transform 0.3s, box-shadow 0.3s;
            background: white;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
        }
        
        .card-header {
            background: var(--primary);
            color: white;
            border-radius: 12px 12px 0 0 !important;
            padding: 15px 20px;
            font-weight: 600;
        }
        
        .stat-card {
            text-align: center;
            padding: 25px 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            transition: transform 0.3s, box-shadow 0.3s;
            border-left: 5px solid var(--primary);
            height: 100%;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
        }
        
        .stat-card.agi64 { border-left-color: #e74c3c; }
        .stat-card.agi65 { border-left-color: #3498db; }
        .stat-card.agi66 { border-left-color: #2ecc71; }
        .stat-card.agi67 { border-left-color: #f39c12; }
        
        .stat-card .number {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .stat-card.agi64 .number { color: #e74c3c; }
        .stat-card.agi65 .number { color: #3498db; }
        .stat-card.agi66 .number { color: #2ecc71; }
        .stat-card.agi67 .number { color: #f39c12; }
        
        .table th {
            background: var(--primary);
            color: white;
        }
        
        .action-buttons .btn {
            padding: 6px 10px;
            margin: 2px;
            border-radius: 6px;
        }
        
        .section-title {
            color: var(--dark);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 3px solid var(--accent);
            font-weight: 600;
        }
        
        .map-container {
            height: 600px;
            border-radius: 10px;
            overflow: hidden;
            border: 2px solid #e9ecef;
        }
        
        .table-controls {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        
        .grade-badge {
            font-size: 0.8rem;
            padding: 4px 8px;
            border-radius: 20px;
        }
        
        .overview-header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
        }
        
        .map-legend {
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 1000;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
            font-size: 12px;
        }
        
        .legend-color {
            width: 15px;
            height: 15px;
            border-radius: 50%;
            margin-right: 8px;
            border: 2px solid white;
            box-shadow: 0 1px 3px rgba(0,0,0,0.2);
        }
        
        .map-controls {
            position: absolute;
            top: 10px;
            left: 10px;
            z-index: 1000;
            background: white;
            padding: 10px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .btn-primary {
            background-color: var(--primary);
            border-color: var(--primary);
        }
        
        .btn-primary:hover {
            background-color: var(--secondary);
            border-color: var(--secondary);
        }
        
        .btn-success {
            background-color: var(--secondary);
            border-color: var(--secondary);
        }
        
        .footer {
            background: var(--dark);
            color: white;
            padding: 30px 0;
            margin-top: 50px;
        }
        
        .feature-icon {
            font-size: 2.5rem;
            color: var(--primary);
            margin-bottom: 15px;
        }
        
        .feature-card {
            text-align: center;
            padding: 30px 20px;
            height: 100%;
        }
        
        .welcome-section {
            padding: 40px 0;
            background: linear-gradient(to right, #f8f9fa, #e9ecef);
            border-radius: 12px;
            margin-bottom: 40px;
        }
        
        .chart-container {
            height: 300px;
            position: relative;
        }
        
        .temp-marker {
            animation: pulse 1.5s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.2); }
            100% { transform: scale(1); }
        }
        
        .popup-edit-btn {
            margin-top: 10px;
            width: 100%;
        }
        
        .add-point-active {
            background-color: var(--secondary) !important;
            color: white !important;
        }
        
        .chart-controls {
            background: white;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1><i class="fas fa-seedling me-2"></i>ระบบจัดการข้อมูลนิสิตคณะเกษตรศาสตร์</h1>
                    <p>ระบบบริหารจัดการข้อมูลนิสิตและแสดงแผนที่ภูมิศาสตร์</p>
                </div>
                <div class="col-md-4 text-end">
                    <div class="display-4 fw-bold"><?php echo array_sum($overview_stats); ?></div>
                    <small>นิสิตทั้งหมดในระบบ</small>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Navigation Tabs -->
        <ul class="nav nav-tabs justify-content-center" id="mainTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="overview-tab" data-bs-toggle="tab" data-bs-target="#overview" type="button" role="tab">
                    <i class="fas fa-tachometer-alt me-2"></i>ภาพรวมระบบ
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="agi64-tab" data-bs-toggle="tab" data-bs-target="#agi64" type="button" role="tab">
                    <i class="fas fa-users me-2"></i>ข้อมูล AGI64
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="agi65-tab" data-bs-toggle="tab" data-bs-target="#agi65" type="button" role="tab">
                    <i class="fas fa-users me-2"></i>ข้อมูล AGI65
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="agi66-tab" data-bs-toggle="tab" data-bs-target="#agi66" type="button" role="tab">
                    <i class="fas fa-users me-2"></i>ข้อมูล AGI66
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="agi67-tab" data-bs-toggle="tab" data-bs-target="#agi67" type="button" role="tab">
                    <i class="fas fa-users me-2"></i>ข้อมูล AGI67
                </button>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content" id="mainTabsContent">
            <!-- Overview Tab -->
            <div class="tab-pane fade show active" id="overview" role="tabpanel">
                <!-- Welcome Section -->
                <div class="welcome-section">
                    <div class="container">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h2 class="mb-3">ระบบจัดการข้อมูลนิสิต</h2>
                                <p class="lead mb-4">ระบบนี้พัฒนาขึ้นเพื่อบริหารจัดการข้อมูลนิสิตคณะเกษตรศาสตร์ และแสดงข้อมูลทางภูมิศาสตร์ของที่อยู่นิสิตบนแผนที่แบบอินเทอร์แอกทีฟ</p>
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <div class="d-flex align-items-center">
                                            <i class="fas fa-database feature-icon me-3"></i>
                                            <div>
                                                <h5 class="mb-1">จัดการข้อมูล</h5>
                                                <p class="mb-0">เพิ่ม แก้ไข ลบ ข้อมูลนิสิต</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <div class="d-flex align-items-center">
                                            <i class="fas fa-map-marked-alt feature-icon me-3"></i>
                                            <div>
                                                <h5 class="mb-1">แผนที่ภูมิศาสตร์</h5>
                                                <p class="mb-0">แสดงตำแหน่งที่อยู่นิสิตบนแผนที่</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <div class="d-flex align-items-center">
                                            <i class="fas fa-chart-bar feature-icon me-3"></i>
                                            <div>
                                                <h5 class="mb-1">วิเคราะห์ข้อมูล</h5>
                                                <p class="mb-0">สรุปสถิติข้อมูลนิสิต</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <?php foreach ($tables as $table): ?>
                    <div class="col-md-3 mb-4">
                        <div class="stat-card <?php echo $table; ?>">
                            <div class="number"><?php echo $overview_stats[$table]; ?></div>
                            <div class="text-muted">นิสิต <?php echo strtoupper($table); ?></div>
                            <div class="mt-3">
                                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="tab" data-bs-target="#<?php echo $table; ?>">
                                
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Map Section -->
                <div class="table-controls">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <h5 class="mb-0 text-dark">
                                <i class="fas fa-map-marked-alt me-2"></i>
                                แผนที่แสดงที่อยู่นิสิต
                            </h5>
                            <small class="text-muted">ดับเบิลคลิกบนแผนที่เพื่อเพิ่มจุด • คลิกที่จุดเพื่อแก้ไข</small>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex gap-2 justify-content-end">
                                <select id="map-grade-select" class="form-select w-auto">
                                    <option value="all">แสดงทั้งหมด</option>
                                    <option value="agi64">เฉพาะ AGI64</option>
                                    <option value="agi65">เฉพาะ AGI65</option>
                                    <option value="agi66">เฉพาะ AGI66</option>
                                    <option value="agi67">เฉพาะ AGI67</option>
                                </select>
                                <button id="add-point-btn" class="btn btn-success">
                                    <i class="fas fa-plus me-1"></i>เพิ่มจุด
                                </button>
                                <button id="refresh-map" class="btn btn-primary">
                                    <i class="fas fa-sync-alt me-1"></i>โหลดใหม่
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Map -->
                <div class="card">
                    <div class="card-body p-0 position-relative">
                        <div id="overview-map" class="map-container"></div>
                        
                        <!-- Map Controls -->
                        <div class="map-controls">
                            <div class="btn-group-vertical">
                                <button id="zoom-in" class="btn btn-sm btn-light border">
                                    <i class="fas fa-plus"></i>
                                </button>
                                <button id="zoom-out" class="btn btn-sm btn-light border">
                                    <i class="fas fa-minus"></i>
                                </button>
                                <button id="locate-th" class="btn btn-sm btn-light border" title="กลับไปประเทศไทย">
                                    <i class="fas fa-globe-asia"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="map-legend">
                            <div class="legend-item">
                                <div class="legend-color" style="background-color: #e74c3c;"></div>
                                <span>AGI64</span>
                            </div>
                            <div class="legend-item">
                                <div class="legend-color" style="background-color: #3498db;"></div>
                                <span>AGI65</span>
                            </div>
                            <div class="legend-item">
                                <div class="legend-color" style="background-color: #2ecc71;"></div>
                                <span>AGI66</span>
                            </div>
                            <div class="legend-item">
                                <div class="legend-color" style="background-color: #f39c12;"></div>
                                <span>AGI67</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Controls -->
                <div class="chart-controls mt-5">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <h5 class="mb-0 text-dark">
                                <i class="fas fa-chart-bar me-2"></i>
                                สถิติและแผนภูมิข้อมูลนิสิต
                            </h5>
                            <small class="text-muted">เลือกดูข้อมูลตามชั้นปีและประเภทที่ต้องการ</small>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex gap-2 justify-content-end">
                                <select id="chart-grade-select" class="form-select w-auto">
                                    <option value="all">ทั้งหมด</option>
                                    <option value="agi64">AGI64</option>
                                    <option value="agi65">AGI65</option>
                                    <option value="agi66">AGI66</option>
                                    <option value="agi67">AGI67</option>
                                </select>
                                <select id="chart-type-select" class="form-select w-auto">
                                    <option value="province">จังหวัด</option>
                                    <option value="curriculum">หลักสูตร</option>
                                    <option value="faculty">คณะ</option>
                                    <option value="department">ภาควิชา</option>
                                    <option value="school">โรงเรียน</option>
                                </select>
                                <button id="refresh-charts" class="btn btn-primary">
                                    <i class="fas fa-sync-alt me-1"></i>อัพเดท
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Section -->
                <div class="row mt-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>สัดส่วนข้อมูลนิสิต</h5>
                                <span class="badge" style="background-color: #f9f9f9ff; color: black;" id="pie-chart-title">จังหวัด</span>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="pieChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>จำนวนนิสิตตามประเภท</h5>
                                <span class="badge" style="background-color: #f9f9f9ff; color: black;" id="bar-chart-title">จังหวัด</span>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="barChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Summary Table -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-bar me-2"></i>
                            สรุปข้อมูลทั้งหมด
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>ชั้นปี</th>
                                        <th>จำนวนนิสิต</th>
                                        <th>จำนวนหลักสูตร</th>
                                        <th>จำนวนจังหวัด</th>
                                        <th>จำนวนที่มีพิกัด</th>
                                        <th>สถานะ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($tables as $table): ?>
                                    <?php 
                                    try {
                                        $stmt = $pdo->prepare("SELECT COUNT(DISTINCT \"หลักสูตร\") as curriculums, COUNT(DISTINCT \"จังหวัด\") as provinces FROM \"$table\"");
                                        $stmt->execute();
                                        $stats = $stmt->fetch();
                                        $with_coords = is_array($table_details[$table]) ? count($table_details[$table]) : 0;
                                    } catch (PDOException $e) {
                                        $stats = ['curriculums' => 0, 'provinces' => 0];
                                        $with_coords = 0;
                                    }
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo strtoupper($table); ?></strong>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary fs-6"><?php echo $overview_stats[$table]; ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-success"><?php echo $stats['curriculums']; ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-info"><?php echo $stats['provinces']; ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-warning text-dark"><?php echo $with_coords; ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-success">
                                                <i class="fas fa-check me-1"></i>พร้อมใช้งาน
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <tr class="table-primary fw-bold">
                                        <td>รวมทั้งหมด</td>
                                        <td><?php echo array_sum($overview_stats); ?></td>
                                        <td>-</td>
                                        <td>-</td>
                                        <td><?php echo count($all_map_data); ?></td>
                                        <td>ระบบทำงานปกติ</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Individual Table Tabs -->
            <?php foreach ($tables as $table): ?>
            <div class="tab-pane fade" id="<?php echo $table; ?>" role="tabpanel">
                <?php
                // Fetch data for this table
                try {
                    $stmt = $pdo->prepare("SELECT * FROM \"$table\" ORDER BY s_id");
                    $stmt->execute();
                    $students = $stmt->fetchAll();
                } catch (PDOException $e) {
                    $students = [];
                }
                ?>
                
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="section-title m-0">
                        <i class="fas fa-users me-2"></i>
                        จัดการข้อมูลนิสิต - <?php echo strtoupper($table); ?>
                    </h2>
                    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addStudentModal" data-table="<?php echo $table; ?>">
                        <i class="fas fa-plus me-1"></i>เพิ่มนิสิตใหม่
                    </button>
                </div>

                <!-- Statistics -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="number"><?php echo count($students); ?></div>
                            <div class="text-muted">จำนวนนิสิต</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="number">
                                <?php echo countUniqueValues($students, 'หลักสูตร'); ?>
                            </div>
                            <div class="text-muted">จำนวนหลักสูตร</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="number">
                                <?php echo countUniqueValues($students, 'จังหวัด'); ?>
                            </div>
                            <div class="text-muted">จำนวนจังหวัด</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="number">
                                <?php echo countUniqueValues($students, 'จบจากโรงเรียน'); ?>
                            </div>
                            <div class="text-muted">จำนวนโรงเรียน</div>
                        </div>
                    </div>
                </div>

                <!-- Students Table -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>
                            <i class="fas fa-list me-2"></i>
                            รายชื่อนิสิตทั้งหมด (<?php echo count($students); ?> คน)
                        </span>
                        <input type="text" id="searchInput-<?php echo $table; ?>" class="form-control form-control-sm w-auto" placeholder="ค้นหานิสิต...">
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>รหัสนิสิต</th>
                                        <th>ชื่อ-นามสกุล</th>
                                        <th>หลักสูตร</th>
                                        <th>ภาควิชา</th>
                                        <th>จังหวัด</th>
                                        <th>การกระทำ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($students)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-4">
                                                <i class="fas fa-inbox fa-2x text-muted mb-2"></i>
                                                <p class="text-muted">ไม่พบข้อมูลนิสิต</p>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($students as $student): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($student['s_id'] ?? ''); ?></td>
                                                <td><?php echo htmlspecialchars($student['s_name'] ?? ''); ?></td>
                                                <td><?php echo htmlspecialchars($student['หลักสูตร'] ?? ''); ?></td>
                                                <td><?php echo htmlspecialchars($student['ภาควิชา'] ?? ''); ?></td>
                                                <td><?php echo htmlspecialchars($student['จังหวัด'] ?? ''); ?></td>
                                                <td>
                                                    <div class="action-buttons">
                                                        <button class="btn btn-sm btn-warning" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#editStudentModal"
                                                                onclick="editStudent(
                                                                    '<?php echo $table; ?>',
                                                                    '<?php echo $student['s_id']; ?>',
                                                                    '<?php echo addslashes($student['s_name'] ?? ''); ?>',
                                                                    '<?php echo addslashes($student['หลักสูตร'] ?? ''); ?>',
                                                                    '<?php echo addslashes($student['คณะ'] ?? ''); ?>',
                                                                    '<?php echo addslashes($student['ภาควิชา'] ?? ''); ?>',
                                                                    '<?php echo addslashes($student['จบจากโรงเรียน'] ?? ''); ?>',
                                                                    '<?php echo $student['lat'] ?? ''; ?>',
                                                                    '<?php echo $student['long'] ?? ''; ?>',
                                                                    '<?php echo addslashes($student['ตำบล'] ?? ''); ?>',
                                                                    '<?php echo addslashes($student['อำเภอ'] ?? ''); ?>',
                                                                    '<?php echo addslashes($student['จังหวัด'] ?? ''); ?>'
                                                                )">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <form method="POST" style="display: inline;" onsubmit="return confirm('แน่ใจว่าต้องการลบข้อมูลนี้?')">
                                                            <input type="hidden" name="action" value="delete">
                                                            <input type="hidden" name="table" value="<?php echo $table; ?>">
                                                            <input type="hidden" name="student_id" value="<?php echo $student['s_id']; ?>">
                                                            <button type="submit" class="btn btn-sm btn-danger">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Add Student Modal -->
    <div class="modal fade" id="addStudentModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">เพิ่มข้อมูลนิสิตใหม่</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="addStudentForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create">
                        <input type="hidden" name="table" id="add_table" value="">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">รหัสนิสิต *</label>
                                    <input type="text" class="form-control" name="s_id" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">ชื่อ-นามสกุล *</label>
                                    <input type="text" class="form-control" name="s_name" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">หลักสูตร</label>
                                    <input type="text" class="form-control" name="หลักสูตร">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">คณะ</label>
                                    <input type="text" class="form-control" name="คณะ">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">ภาควิชา</label>
                                    <input type="text" class="form-control" name="ภาควิชา">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">โรงเรียนที่จบ</label>
                                    <input type="text" class="form-control" name="จบจากโรงเรียน">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">ละติจูด</label>
                                    <input type="number" step="any" class="form-control" name="lat" id="add_lat">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">ลองจิจูด</label>
                                    <input type="number" step="any" class="form-control" name="long" id="add_long">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">ตำบล</label>
                                    <input type="text" class="form-control" name="ตำบล">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">อำเภอ</label>
                                    <input type="text" class="form-control" name="อำเภอ">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">จังหวัด</label>
                                    <input type="text" class="form-control" name="จังหวัด">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
                        <button type="submit" class="btn btn-primary">บันทึกข้อมูล</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Student Modal -->
    <div class="modal fade" id="editStudentModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">แก้ไขข้อมูลนิสิต</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="editStudentForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="table" id="edit_table">
                        <input type="hidden" name="student_id" id="edit_student_id">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">ชื่อ-นามสกุล *</label>
                                    <input type="text" class="form-control" name="s_name" id="edit_s_name" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">หลักสูตร</label>
                                    <input type="text" class="form-control" name="หลักสูตร" id="edit_หลักสูตร">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">คณะ</label>
                                    <input type="text" class="form-control" name="คณะ" id="edit_คณะ">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">ภาควิชา</label>
                                    <input type="text" class="form-control" name="ภาควิชา" id="edit_ภาควิชา">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">โรงเรียนที่จบ</label>
                                    <input type="text" class="form-control" name="จบจากโรงเรียน" id="edit_จบจากโรงเรียน">
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">ละติจูด</label>
                                            <input type="number" step="any" class="form-control" name="lat" id="edit_lat">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">ลองจิจูด</label>
                                            <input type="number" step="any" class="form-control" name="long" id="edit_long">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">ตำบล</label>
                                    <input type="text" class="form-control" name="ตำบล" id="edit_ตำบล">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">อำเภอ</label>
                                    <input type="text" class="form-control" name="อำเภอ" id="edit_อำเภอ">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">จังหวัด</label>
                                    <input type="text" class="form-control" name="จังหวัด" id="edit_จังหวัด">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
                        <button type="submit" class="btn btn-primary">อัพเดทข้อมูล</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        // Set table when opening add student modal
        document.getElementById('addStudentModal').addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const table = button.getAttribute('data-table');
            document.getElementById('add_table').value = table;
        });

        // Search functionality for each table
        <?php foreach ($tables as $table): ?>
        document.getElementById('searchInput-<?php echo $table; ?>')?.addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const tableBody = document.querySelector('#<?php echo $table; ?> tbody');
            const rows = tableBody.querySelectorAll('tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });
        <?php endforeach; ?>

        // Edit student function
        function editStudent(table, s_id, s_name, หลักสูตร, คณะ, ภาควิชา, จบจากโรงเรียน, lat, long, ตำบล, อำเภอ, จังหวัด) {
            document.getElementById('edit_table').value = table;
            document.getElementById('edit_student_id').value = s_id;
            document.getElementById('edit_s_name').value = s_name || '';
            document.getElementById('edit_หลักสูตร').value = หลักสูตร || '';
            document.getElementById('edit_คณะ').value = คณะ || '';
            document.getElementById('edit_ภาควิชา').value = ภาควิชา || '';
            document.getElementById('edit_จบจากโรงเรียน').value = จบจากโรงเรียน || '';
            document.getElementById('edit_lat').value = lat || '';
            document.getElementById('edit_long').value = long || '';
            document.getElementById('edit_ตำบล').value = ตำบล || '';
            document.getElementById('edit_อำเภอ').value = อำเภอ || '';
            document.getElementById('edit_จังหวัด').value = จังหวัด || '';
        }

        // Map functionality for overview page
        let map = null;
        let markers = [];
        let tempMarker = null;
        let isAddingPoint = false;

        // Earth Tone Colors (ไม่ใช่สีน้ำตาล)
        const earthToneColors = [
            '#2c5530', '#4a7c59', '#8fb996', '#a8c3b8', '#d4e6d4',
            '#6b8e23', '#556b2f', '#8fbc8f', '#98fb98', '#90ee90',
            '#2e8b57', '#3cb371', '#20b2aa', '#48d1cc', '#40e0d0',
            '#00ced1', '#4682b4', '#5f9ea0', '#6495ed', '#7b68ee'
        ];

        // ตัวแปรเก็บ Charts
        let pieChart = null;
        let barChart = null;

        // ข้อมูล Charts จาก PHP
        const chartDataFromPHP = <?php echo json_encode($chart_data, JSON_UNESCAPED_UNICODE); ?>;

        function initMap() {
            map = L.map('overview-map').setView([13.736717, 100.523186], 6);
            
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap contributors'
            }).addTo(map);

            loadMapData('all');
            
            // Add map controls
            document.getElementById('zoom-in')?.addEventListener('click', function() {
                map.zoomIn();
            });

            document.getElementById('zoom-out')?.addEventListener('click', function() {
                map.zoomOut();
            });

            document.getElementById('locate-th')?.addEventListener('click', function() {
                map.setView([13.736717, 100.523186], 6);
            });

            // Add point button functionality
            document.getElementById('add-point-btn')?.addEventListener('click', function() {
                isAddingPoint = true;
                this.classList.add('add-point-active');
                alert('กรุณาคลิกบนแผนที่เพื่อเลือกตำแหน่งที่ต้องการเพิ่มนิสิต');
            });

            // Refresh map button
            document.getElementById('refresh-map')?.addEventListener('click', function() {
                const selectedGrade = document.getElementById('map-grade-select').value;
                loadMapData(selectedGrade);
            });

            // Handle map click for adding points
            map.on('click', async function(e) {
                if (isAddingPoint) {
                    // Remove previous temp marker
                    if (tempMarker) {
                        map.removeLayer(tempMarker);
                    }
                    
                    // Add temporary marker
                    tempMarker = L.marker([e.latlng.lat, e.latlng.lng], {
                        icon: L.divIcon({
                            className: 'temp-marker',
                            html: '<div style="background-color: #e74c3c; width: 20px; height: 20px; border-radius: 50%; border: 3px solid white; box-shadow: 0 0 10px rgba(0,0,0,0.5);"></div>',
                            iconSize: [20, 20]
                        })
                    }).addTo(map);
                    
                    // เพิ่มข้อมูลลงฐานข้อมูล
                    const selectedTable = document.getElementById('map-grade-select').value;
                    if (selectedTable === 'all') {
                        alert('กรุณาเลือกชั้นปีก่อนเพิ่มจุด');
                        isAddingPoint = false;
                        document.getElementById('add-point-btn').classList.remove('add-point-active');
                        return;
                    }
                    
                    // ตั้งค่าพิกัดในฟอร์มเพิ่ม
                    document.getElementById('add_lat').value = e.latlng.lat.toFixed(6);
                    document.getElementById('add_long').value = e.latlng.lng.toFixed(6);
                    document.getElementById('add_table').value = selectedTable;
                    
                    // Reset add point mode
                    isAddingPoint = false;
                    document.getElementById('add-point-btn').classList.remove('add-point-active');
                    
                    // เปิดฟอร์มเพิ่มนิสิต
                    const addModal = new bootstrap.Modal(document.getElementById('addStudentModal'));
                    addModal.show();
                }
            });

            // Handle double click to add point (alternative method)
            map.on('dblclick', function(e) {
                // เปิดโหมดเพิ่มจุดอัตโนมัติเมื่อดับเบิลคลิก
                isAddingPoint = true;
                document.getElementById('add-point-btn').classList.add('add-point-active');
                
                // สร้าง marker ชั่วคราว
                if (tempMarker) {
                    map.removeLayer(tempMarker);
                }
                
                tempMarker = L.marker([e.latlng.lat, e.latlng.lng], {
                    icon: L.divIcon({
                        className: 'temp-marker',
                        html: '<div style="background-color: #e74c3c; width: 20px; height: 20px; border-radius: 50%; border: 3px solid white; box-shadow: 0 0 10px rgba(0,0,0,0.5);"></div>',
                        iconSize: [20, 20]
                    })
                }).addTo(map);
                
                // ตั้งค่าพิกัดในฟอร์ม
                const selectedTable = document.getElementById('map-grade-select').value;
                if (selectedTable !== 'all') {
                    document.getElementById('add_lat').value = e.latlng.lat.toFixed(6);
                    document.getElementById('add_long').value = e.latlng.lng.toFixed(6);
                    document.getElementById('add_table').value = selectedTable;
                    
                    // เปิดฟอร์มเพิ่มนิสิต
                    const addModal = new bootstrap.Modal(document.getElementById('addStudentModal'));
                    addModal.show();
                } else {
                    alert('กรุณาเลือกชั้นปีก่อนเพิ่มจุด');
                }
                
                // Reset add point mode
                isAddingPoint = false;
                document.getElementById('add-point-btn').classList.remove('add-point-active');
            });
        }

        function loadMapData(grade) {
            // Clear existing markers
            markers.forEach(marker => map.removeLayer(marker));
            markers = [];

            const allMapData = <?php echo json_encode($all_map_data); ?>;
            
            let filteredData = allMapData;
            if (grade !== 'all') {
                filteredData = allMapData.filter(student => student.table_name === grade);
            }

            filteredData.forEach(student => {
                if (student.lat && student.long && student.lat != 0 && student.long != 0) {
                    const gradeColor = getGradeColor(student.table_name);
                    const marker = L.circleMarker([student.lat, student.long], {
                        radius: 8,
                        fillColor: gradeColor,
                        color: '#fff',
                        weight: 2,
                        opacity: 1,
                        fillOpacity: 0.8
                    }).addTo(map);

                    // Create edit button for popup
                    const editButton = `<button class="btn btn-sm btn-warning popup-edit-btn" 
                        onclick="editStudentFromMap('${student.table_name}', '${student.s_id}')">
                        <i class="fas fa-edit me-1"></i>แก้ไขข้อมูล
                    </button>`;

                    marker.bindPopup(`
                        <div style="min-width: 200px;">
                            <h6 style="margin: 0 0 5px 0; color: #2c3e50;">${student.s_name || ''}</h6>
                            <p style="margin: 2px 0; font-size: 12px;"><strong>รหัส:</strong> ${student.s_id || ''}</p>
                            <p style="margin: 2px 0; font-size: 12px;"><strong>ชั้นปี:</strong> ${student.table_name ? student.table_name.toUpperCase() : ''}</p>
                            <p style="margin: 2px 0; font-size: 12px;"><strong>หลักสูตร:</strong> ${student.หลักสูตร || ''}</p>
                            <p style="margin: 2px 0; font-size: 12px;"><strong>จังหวัด:</strong> ${student.จังหวัด || ''}</p>
                            ${editButton}
                        </div>
                    `);
                    markers.push(marker);
                }
            });

            // Fit bounds to show all markers
            if (markers.length > 0) {
                const group = new L.featureGroup(markers);
                map.fitBounds(group.getBounds().pad(0.1));
            }
        }

        function getGradeColor(grade) {
            const colors = {
                'agi64': '#e74c3c',
                'agi65': '#3498db',
                'agi66': '#2ecc71',
                'agi67': '#f39c12'
            };
            return colors[grade] || '#95a5a6';
        }

        // Function to edit student from map popup
        async function editStudentFromMap(table, studentId) {
            try {
                const response = await fetch(`api/get_student_data.php?table=${table}&student_id=${studentId}`);
                const result = await response.json();
                
                if (result.success) {
                    const student = result.data;
                    
                    document.getElementById('edit_table').value = table;
                    document.getElementById('edit_student_id').value = student.s_id;
                    document.getElementById('edit_s_name').value = student.s_name || '';
                    document.getElementById('edit_หลักสูตร').value = student.หลักสูตร || '';
                    document.getElementById('edit_คณะ').value = student.คณะ || '';
                    document.getElementById('edit_ภาควิชา').value = student.ภาควิชา || '';
                    document.getElementById('edit_จบจากโรงเรียน').value = student['จบจากโรงเรียน'] || '';
                    document.getElementById('edit_lat').value = student.lat || '';
                    document.getElementById('edit_long').value = student.long || '';
                    document.getElementById('edit_ตำบล').value = student.ตำบล || '';
                    document.getElementById('edit_อำเภอ').value = student.อำเภอ || '';
                    document.getElementById('edit_จังหวัด').value = student.จังหวัด || '';

                    // Open edit modal
                    const editModal = new bootstrap.Modal(document.getElementById('editStudentModal'));
                    editModal.show();
                } else {
                    alert('ไม่สามารถโหลดข้อมูลนิสิต: ' + result.message);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('เกิดข้อผิดพลาดในการโหลดข้อมูล');
            }
        }

        // ฟังก์ชันดึงข้อมูลสำหรับ Charts
        function getChartData(grade, dataType) {
            if (grade === 'all') {
                // รวมข้อมูลทุกตาราง
                const allData = {
                    labels: [],
                    data: [],
                    counts: {}
                };
                
                <?php foreach ($tables as $table): ?>
                if (chartDataFromPHP['<?php echo $table; ?>'] && chartDataFromPHP['<?php echo $table; ?>'][dataType]) {
                    chartDataFromPHP['<?php echo $table; ?>'][dataType].forEach(item => {
                        const label = item[Object.keys(item)[0]];
                        const count = item.count;
                        if (label && label.trim() !== '') {
                            allData.counts[label] = (allData.counts[label] || 0) + count;
                        }
                    });
                }
                <?php endforeach; ?>
                
                // เรียงลำดับและเลือก 10 อันดับแรก
                const sorted = Object.entries(allData.counts)
                    .sort(([,a], [,b]) => b - a)
                    .slice(0, 10);
                
                return {
                    labels: sorted.map(([label]) => label),
                    data: sorted.map(([,count]) => count)
                };
            } else {
                // ข้อมูลเฉพาะชั้นปี
                if (chartDataFromPHP[grade] && chartDataFromPHP[grade][dataType]) {
                    const items = chartDataFromPHP[grade][dataType];
                    return {
                        labels: items.map(item => item[Object.keys(item)[0]]).filter(label => label && label.trim() !== ''),
                        data: items.map(item => item.count)
                    };
                }
            }
            
            return { labels: [], data: [] };
        }

        // ฟังก์ชันอัพเดท Charts
        function updateCharts() {
            const grade = document.getElementById('chart-grade-select').value;
            const dataType = document.getElementById('chart-type-select').value;
            
            const chartData = getChartData(grade, dataType);
            
            // อัพเดทหัวข้อ Charts
            const typeNames = {
                'province': 'จังหวัด',
                'curriculum': 'หลักสูตร',
                'faculty': 'คณะ',
                'department': 'ภาควิชา',
                'school': 'โรงเรียน'
            };
            
            const gradeNames = {
                'all': 'ทั้งหมด',
                'agi64': 'AGI64',
                'agi65': 'AGI65',
                'agi66': 'AGI66',
                'agi67': 'AGI67'
            };
            
            document.getElementById('pie-chart-title').textContent = typeNames[dataType];
            document.getElementById('bar-chart-title').textContent = typeNames[dataType];
            
            // อัพเดท Pie Chart
            updatePieChart(chartData, `${gradeNames[grade]} - ${typeNames[dataType]}`);
            
            // อัพเดท Bar Chart
            updateBarChart(chartData, `${gradeNames[grade]} - ${typeNames[dataType]}`);
        }

        // ฟังก์ชันอัพเดท Pie Chart
        function updatePieChart(chartData, title) {
            const pieCtx = document.getElementById('pieChart').getContext('2d');
            
            // ทำลาย Chart เดิมถ้ามี
            if (pieChart) {
                pieChart.destroy();
            }
            
            pieChart = new Chart(pieCtx, {
                type: 'pie',
                data: {
                    labels: chartData.labels,
                    datasets: [{
                        data: chartData.data,
                        backgroundColor: earthToneColors.slice(0, chartData.labels.length),
                        borderWidth: 1,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: {
                                boxWidth: 12,
                                font: {
                                    size: 11
                                }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.raw || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = Math.round((value / total) * 100);
                                    return `${label}: ${value} คน (${percentage}%)`;
                                }
                            }
                        },
                        title: {
                            display: true,
                            text: title,
                            font: {
                                size: 14,
                                weight: 'bold'
                            }
                        }
                    }
                }
            });
        }

        // ฟังก์ชันอัพเดท Bar Chart
        function updateBarChart(chartData, title) {
            const barCtx = document.getElementById('barChart').getContext('2d');
            
            // ทำลาย Chart เดิมถ้ามี
            if (barChart) {
                barChart.destroy();
            }
            
            barChart = new Chart(barCtx, {
                type: 'bar',
                data: {
                    labels: chartData.labels,
                    datasets: [{
                        label: 'จำนวนนิสิต',
                        data: chartData.data,
                        backgroundColor: earthToneColors[0],
                        borderWidth: 1,
                        borderRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'จำนวนนิสิต'
                            },
                            ticks: {
                                stepSize: 1
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: title.split(' - ')[1]
                            },
                            ticks: {
                                maxRotation: 45,
                                minRotation: 45
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return `จำนวนนิสิต: ${context.raw} คน`;
                                }
                            }
                        }
                    }
                }
            });
        }

        // ฟังก์ชันเริ่มต้น Charts
        function initCharts() {
            // โหลด Charts ครั้งแรก
            updateCharts();
            
            // Event listeners สำหรับการเปลี่ยนเลือก
            document.getElementById('chart-grade-select').addEventListener('change', updateCharts);
            document.getElementById('chart-type-select').addEventListener('change', updateCharts);
            document.getElementById('refresh-charts').addEventListener('click', updateCharts);
        }

        // Initialize map and charts when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            initMap();
            initCharts();
            
            // Add event listeners for map controls
            document.getElementById('map-grade-select')?.addEventListener('change', function() {
                const selectedGrade = this.value;
                loadMapData(selectedGrade);
            });
        });
    </script>
</body>
</html>