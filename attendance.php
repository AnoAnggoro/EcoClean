<?php
require_once 'functions.php';
requireLogin();

// Handle attendance submission
if ($_POST && isset($_POST['record_attendance'])) {
    $nip = $_POST['nip'];
    $date = $_POST['date'];
    $check_in = $_POST['check_in'];
    $check_out = $_POST['check_out'];
    $status = $_POST['status'];
    
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO attendance (nip, date, check_in, check_out, status) VALUES (?, ?, ?, ?, ?)");
    if ($stmt->execute([$nip, $date, $check_in, $check_out, $status])) {
        header('Location: attendance.php?success=1');
        exit;
    }
}

// Handle edit attendance
if ($_POST && isset($_POST['edit_attendance'])) {
    $id = $_POST['id'];
    $nip = $_POST['nip'];
    $date = $_POST['date'];
    $check_in = $_POST['check_in'];
    $check_out = $_POST['check_out'];
    $status = $_POST['status'];
    
    global $pdo;
    $stmt = $pdo->prepare("UPDATE attendance SET nip=?, date=?, check_in=?, check_out=?, status=? WHERE id=?");
    if ($stmt->execute([$nip, $date, $check_in, $check_out, $status, $id])) {
        header('Location: attendance.php?success=2');
        exit;
    }
}

// Handle delete
if (isset($_GET['delete']) && $_GET['delete']) {
    global $pdo;
    $stmt = $pdo->prepare("DELETE FROM attendance WHERE id = ?");
    if ($stmt->execute([$_GET['delete']])) {
        header('Location: attendance.php?success=3');
        exit;
    }
}

// Get attendance data with pagination
$page = $_GET['page'] ?? 1;
$limit = 10;
$offset = ($page - 1) * $limit;

global $pdo;

// Handle search and filters
$search = $_GET['search'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$status_filter = $_GET['status_filter'] ?? '';
$department_filter = $_GET['department_filter'] ?? '';

// Build query with filters
$sql = "
    SELECT a.*, e.name, e.department, e.position
    FROM attendance a 
    LEFT JOIN employees e ON a.nip = e.nip 
    WHERE 1=1
";

$params = [];

if ($search) {
    $sql .= " AND (a.nip LIKE ? OR e.name LIKE ?)";
    $searchParam = "%$search%";
    $params = array_merge($params, [$searchParam, $searchParam]);
}

if ($date_from) {
    $sql .= " AND a.date >= ?";
    $params[] = $date_from;
}

if ($date_to) {
    $sql .= " AND a.date <= ?";
    $params[] = $date_to;
}

if ($status_filter) {
    $sql .= " AND a.status = ?";
    $params[] = $status_filter;
}

if ($department_filter) {
    $sql .= " AND e.department = ?";
    $params[] = $department_filter;
}

$sql .= " ORDER BY a.date DESC, a.check_in DESC LIMIT $limit OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$attendanceData = $stmt->fetchAll();

// Get total records for pagination
$total = $pdo->query("SELECT COUNT(*) FROM attendance")->fetchColumn();
$totalPages = ceil($total / $limit);

// Get employees for dropdown
$employees = $pdo->query("SELECT nip, name, department FROM employees WHERE status = 'active'")->fetchAll();

// Get departments for filter
$departments = $pdo->query("SELECT DISTINCT department FROM employees WHERE department IS NOT NULL ORDER BY department")->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Absensi - PT. CHICKDREAM</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <?php echo renderSidebar('attendance'); ?>
        
        <!-- Main Content -->
        <div class="main-content">
            <?php echo renderTopBar('Absensi'); ?>
            
            <div class="content">
                <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success">
                        <?php 
                        $messages = [
                            1 => 'Data absensi berhasil dicatat!',
                            2 => 'Data absensi berhasil diperbarui!',
                            3 => 'Data absensi berhasil dihapus!'
                        ];
                        echo $messages[$_GET['success']];
                        ?>
                    </div>
                <?php endif; ?>
                
                <!-- Tabel Data Absensi -->
                <div class="data-table">
                    <div class="section-header">
                        <h4>Riwayat Absensi</h4>
                        <div class="table-controls">
                            <button class="btn-add" onclick="openModal('addModal')">Tambah Data</button>
                            <button class="btn-add" onclick="window.print()">Print Report</button>
                            <input type="text" placeholder="Search..." id="searchInput">
                        </div>
                    </div>
                    
                    <!-- Search and Filter Section -->
                    <div class="search-filter-section no-print">
                        <form method="GET" class="search-form">
                            <input type="hidden" name="page" value="<?php echo $page; ?>">
                            <div class="search-row">
                                <div class="search-group">
                                    <input type="text" name="search" placeholder="Cari NIP atau Nama..." 
                                           value="<?php echo htmlspecialchars($search); ?>" class="search-input">
                                </div>
                                <div class="filter-group">
                                    <input type="date" name="date_from" class="filter-select" 
                                           value="<?php echo $date_from; ?>" placeholder="Dari Tanggal">
                                </div>
                                <div class="filter-group">
                                    <input type="date" name="date_to" class="filter-select" 
                                           value="<?php echo $date_to; ?>" placeholder="Sampai Tanggal">
                                </div>
                                <div class="filter-group">
                                    <select name="status_filter" class="filter-select">
                                        <option value="">Semua Status</option>
                                        <option value="hadir" <?php echo $status_filter == 'hadir' ? 'selected' : ''; ?>>Hadir</option>
                                        <option value="sakit" <?php echo $status_filter == 'sakit' ? 'selected' : ''; ?>>Sakit</option>
                                        <option value="izin" <?php echo $status_filter == 'izin' ? 'selected' : ''; ?>>Izin</option>
                                        <option value="alpha" <?php echo $status_filter == 'alpha' ? 'selected' : ''; ?>>Alpha</option>
                                    </select>
                                </div>
                                <div class="search-buttons">
                                    <button type="submit" class="btn-primary">Cari</button>
                                    <button type="button" class="btn-secondary" onclick="resetFilters()">Reset</button>
                                </div>
                            </div>
                        </form>
                        
                        <?php if ($search || $date_from || $date_to || $status_filter || $department_filter): ?>
                        <div class="search-results-info">
                            <p>Ditemukan <?php echo count($attendanceData); ?> data absensi</p>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="responsive-table-wrapper">
                        <table class="attendance-table">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Tanggal</th>
                                    <th>NIP</th>
                                    <th>Nama</th>
                                    <th class="tablet-only desktop-only">Jam Masuk</th>
                                    <th class="tablet-only desktop-only">Jam Keluar</th>
                                    <th>Status</th>
                                    <th class="no-print">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($attendanceData as $index => $record): ?>
                                <tr>
                                    <td><?php echo ($page - 1) * $limit + $index + 1; ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($record['date'])); ?></td>
                                    <td><?php echo $record['nip']; ?></td>
                                    <td>
                                        <div class="employee-info">
                                            <strong><?php echo $record['name']; ?></strong>
                                            <small class="mobile-only">
                                                <?php if ($record['check_in'] || $record['check_out']): ?>
                                                    <?php echo $record['check_in'] ?: '-'; ?> - <?php echo $record['check_out'] ?: '-'; ?>
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                    </td>
                                    <td class="tablet-only desktop-only"><?php echo $record['check_in'] ?: '-'; ?></td>
                                    <td class="tablet-only desktop-only"><?php echo $record['check_out'] ?: '-'; ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $record['status']; ?>">
                                            <?php echo ucfirst($record['status']); ?>
                                        </span>
                                    </td>
                                    <td class="no-print">
                                        <div class="action-buttons-mobile">
                                            <button onclick="viewAttendance(<?php echo $record['id']; ?>)" class="btn-action btn-view" title="Detail">👁</button>
                                            <button onclick="editAttendance(<?php echo $record['id']; ?>)" class="btn-action btn-edit" title="Edit">✏️</button>
                                            <a href="?delete=<?php echo $record['id']; ?>" class="btn-action btn-delete" 
                                               onclick="return confirm('Yakin ingin menghapus data ini?')" title="Hapus">🗑</a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                    <div class="pagination">
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <a href="?page=<?php echo $i; ?>" class="<?php echo $i == $page ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="footer">
                PT. CHICKDREAM MULYA JADI WONOSOBO
            </div>
        </div>
    </div>

    <!-- Modal Tambah Data -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h4>Input Data Absensi</h4>
                <span class="close" onclick="closeModal('addModal')">&times;</span>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Pilih Pegawai</label>
                            <select name="nip" class="form-control" required>
                                <option value="">-- Pilih Pegawai --</option>
                                <?php foreach ($employees as $emp): ?>
                                    <option value="<?php echo $emp['nip']; ?>">
                                        <?php echo $emp['nip']; ?> - <?php echo $emp['name']; ?> (<?php echo $emp['department']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Tanggal</label>
                            <input type="date" name="date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Jam Masuk</label>
                            <input type="time" name="check_in" class="form-control">
                        </div>
                        <div class="form-group">
                            <label>Jam Keluar</label>
                            <input type="time" name="check_out" class="form-control">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Status Kehadiran</label>
                            <select name="status" class="form-control" required>
                                <option value="hadir">Hadir</option>
                                <option value="sakit">Sakit</option>
                                <option value="izin">Izin</option>
                                <option value="alpha">Alpha</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="submit" name="record_attendance" class="btn-primary">Catat Absensi</button>
                    <button type="button" class="btn-secondary" onclick="closeModal('addModal')">Batal</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Edit Data -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h4>Edit Data Absensi</h4>
                <span class="close" onclick="closeModal('editModal')">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-body">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Pilih Pegawai</label>
                            <select name="nip" id="edit_nip" class="form-control" required>
                                <option value="">-- Pilih Pegawai --</option>
                                <?php foreach ($employees as $emp): ?>
                                    <option value="<?php echo $emp['nip']; ?>">
                                        <?php echo $emp['nip']; ?> - <?php echo $emp['name']; ?> (<?php echo $emp['department']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Tanggal</label>
                            <input type="date" name="date" id="edit_date" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Jam Masuk</label>
                            <input type="time" name="check_in" id="edit_check_in" class="form-control">
                        </div>
                        <div class="form-group">
                            <label>Jam Keluar</label>
                            <input type="time" name="check_out" id="edit_check_out" class="form-control">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Status Kehadiran</label>
                            <select name="status" id="edit_status" class="form-control" required>
                                <option value="hadir">Hadir</option>
                                <option value="sakit">Sakit</option>
                                <option value="izin">Izin</option>
                                <option value="alpha">Alpha</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="submit" name="edit_attendance" class="btn-primary">Update Absensi</button>
                    <button type="button" class="btn-secondary" onclick="closeModal('editModal')">Batal</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal View Detail -->
    <div id="viewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h4>Detail Data Absensi</h4>
                <span class="close" onclick="closeModal('viewModal')">&times;</span>
            </div>
            <div class="modal-body">
                <div id="viewContent">
                    <!-- Content will be loaded here -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal('viewModal')">Tutup</button>
            </div>
        </div>
    </div>

    <script>
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        function editAttendance(id) {
            // Fetch attendance data and populate edit form
            fetch(`get_attendance.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('edit_id').value = data.id;
                    document.getElementById('edit_nip').value = data.nip;
                    document.getElementById('edit_date').value = data.date;
                    document.getElementById('edit_check_in').value = data.check_in;
                    document.getElementById('edit_check_out').value = data.check_out;
                    document.getElementById('edit_status').value = data.status;
                    
                    openModal('editModal');
                })
                .catch(error => {
                    alert('Error loading data: ' + error);
                });
        }
        
        function viewAttendance(id) {
            // Fetch attendance data and show in view modal
            fetch(`get_attendance.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    const statusLabels = {
                        'hadir': 'Hadir',
                        'sakit': 'Sakit',
                        'izin': 'Izin',
                        'alpha': 'Alpha'
                    };
                    
                    const content = `
                        <div class="detail-row">
                            <label>Tanggal:</label>
                            <span>${new Date(data.date).toLocaleDateString('id-ID')}</span>
                        </div>
                        <div class="detail-row">
                            <label>NIP:</label>
                            <span>${data.nip}</span>
                        </div>
                        <div class="detail-row">
                            <label>Nama Pegawai:</label>
                            <span>${data.name || '-'}</span>
                        </div>
                        <div class="detail-row">
                            <label>Departemen:</label>
                            <span>${data.department || '-'}</span>
                        </div>
                        <div class="detail-row">
                            <label>Jabatan:</label>
                            <span>${data.position || '-'}</span>
                        </div>
                        <div class="detail-row">
                            <label>Jam Masuk:</label>
                            <span>${data.check_in || '-'}</span>
                        </div>
                        <div class="detail-row">
                            <label>Jam Keluar:</label>
                            <span>${data.check_out || '-'}</span>
                        </div>
                        <div class="detail-row">
                            <label>Status Kehadiran:</label>
                            <span>${statusLabels[data.status] || data.status}</span>
                        </div>
                        <div class="detail-row">
                            <label>Waktu Input:</label>
                            <span>${new Date(data.created_at).toLocaleString('id-ID')}</span>
                        </div>
                    `;
                    
                    document.getElementById('viewContent').innerHTML = content;
                    openModal('viewModal');
                })
                .catch(error => {
                    alert('Error loading data: ' + error);
                });
        }
        
        function resetFilters() {
            window.location.href = 'attendance.php';
        }
        
        // Simple search functionality
        document.getElementById('searchInput').addEventListener('keyup', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = document.querySelectorAll('tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });
        }
        
        // Initialize responsive features
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize mobile sidebar
            if (typeof initMobileSidebar === 'function') {
                initMobileSidebar();
            }
            
            // Mobile-specific enhancements
            if (window.innerWidth < 768) {
                // Optimize modal size for mobile
                const modals = document.querySelectorAll('.modal-content');
                modals.forEach(modal => {
                    modal.style.width = '95%';
                    modal.style.maxHeight = '90vh';
                    modal.style.overflow = 'auto';
                });
                
                // Enhance form inputs for mobile
                const inputs = document.querySelectorAll('input, select, textarea');
                inputs.forEach(input => {
                    input.addEventListener('focus', function() {
                        setTimeout(() => {
                            this.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        }, 300);
                    });
                });
            }
        });
    </script>
</body>
</html>
