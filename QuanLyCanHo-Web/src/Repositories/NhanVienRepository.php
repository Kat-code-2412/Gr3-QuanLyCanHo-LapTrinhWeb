<?php
declare(strict_types=1);

final class NhanVienRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * Lấy danh sách nhân viên có tìm kiếm, lọc theo vai trò, trạng thái và phân trang.
     */
    public function all(
        string $keyword = '',
        ?string $role = null,
        ?string $status = null,
        int $page = 1,
        int $perPage = 10
    ): array {
        $page = max(1, $page);
        $perPage = min(100, max(5, $perPage));
        $offset = ($page - 1) * $perPage;

        $conditions = [];
        $params = [];

        if ($keyword !== '') {
            $conditions[] = '(HoTen LIKE :kw_name OR TenDangNhap LIKE :kw_user OR Email LIKE :kw_email OR SoDienThoai LIKE :kw_phone)';
            $kw = '%' . $keyword . '%';
            $params['kw_name'] = $kw;
            $params['kw_user'] = $kw;
            $params['kw_email'] = $kw;
            $params['kw_phone'] = $kw;
        }

        if (!empty($role)) {
            $conditions[] = 'VaiTro = :role';
            $params['role'] = $role;
        }

        if (!empty($status)) {
            $conditions[] = 'TrangThai = :status';
            $params['status'] = $status;
        }

        $where = $conditions ? ('WHERE ' . implode(' AND ', $conditions)) : '';

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM NhanVien {$where}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $stmt = $this->pdo->prepare(
            "SELECT MaNV, HoTen, TenDangNhap, VaiTro, SoDienThoai, Email, TrangThai
             FROM NhanVien {$where}
             ORDER BY MaNV DESC LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);

        return [
            'items'   => $stmt->fetchAll(),
            'total'   => $total,
            'page'    => $page,
            'perPage' => $perPage,
        ];
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT MaNV, HoTen, TenDangNhap, MatKhau, VaiTro, SoDienThoai, Email, TrangThai
             FROM NhanVien WHERE MaNV = :id'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Tìm nhân viên theo tên đăng nhập (hỗ trợ loại trừ id khi sửa).
     */
    public function findByUsername(string $username, ?int $excludeId = null): ?array
    {
        $sql = 'SELECT MaNV, HoTen, TenDangNhap FROM NhanVien WHERE TenDangNhap = :username';
        $params = ['username' => $username];

        if ($excludeId !== null) {
            $sql .= ' AND MaNV != :excludeId';
            $params['excludeId'] = $excludeId;
        }

        $sql .= ' LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Tìm nhân viên theo email (hỗ trợ loại trừ id khi sửa).
     */
    public function findByEmail(string $email, ?int $excludeId = null): ?array
    {
        $sql = 'SELECT MaNV, HoTen, Email FROM NhanVien WHERE Email = :email';
        $params = ['email' => $email];

        if ($excludeId !== null) {
            $sql .= ' AND MaNV != :excludeId';
            $params['excludeId'] = $excludeId;
        }

        $sql .= ' LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Kiểm tra xem nhân viên có đang được tham chiếu bởi các Hợp đồng hay không.
     */
    public function hasRelatedContracts(int $id): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM HopDong WHERE MaNV = :id');
        $stmt->execute(['id' => $id]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO NhanVien
             (HoTen, TenDangNhap, MatKhau, VaiTro, SoDienThoai, Email, TrangThai)
             VALUES (:HoTen, :TenDangNhap, :MatKhau, :VaiTro, :SoDienThoai, :Email, :TrangThai)'
        );
        $stmt->execute([
            'HoTen'       => $data['HoTen'],
            'TenDangNhap' => $data['TenDangNhap'],
            'MatKhau'     => $data['MatKhau'],
            'VaiTro'      => $data['VaiTro'],
            'SoDienThoai' => $data['SoDienThoai'] !== '' ? $data['SoDienThoai'] : null,
            'Email'       => $data['Email'] !== '' ? $data['Email'] : null,
            'TrangThai'   => $data['TrangThai'],
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $fields = [
            'HoTen = :HoTen',
            'TenDangNhap = :TenDangNhap',
            'VaiTro = :VaiTro',
            'SoDienThoai = :SoDienThoai',
            'Email = :Email',
            'TrangThai = :TrangThai',
        ];

        $params = [
            'id'          => $id,
            'HoTen'       => $data['HoTen'],
            'TenDangNhap' => $data['TenDangNhap'],
            'VaiTro'      => $data['VaiTro'],
            'SoDienThoai' => $data['SoDienThoai'] !== '' ? $data['SoDienThoai'] : null,
            'Email'       => $data['Email'] !== '' ? $data['Email'] : null,
            'TrangThai'   => $data['TrangThai'],
        ];

        if (!empty($data['MatKhau'])) {
            $fields[] = 'MatKhau = :MatKhau';
            $params['MatKhau'] = $data['MatKhau'];
        }

        $stmt = $this->pdo->prepare('UPDATE NhanVien SET ' . implode(', ', $fields) . ' WHERE MaNV = :id');
        $stmt->execute($params);
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM NhanVien WHERE MaNV = :id');
        $stmt->execute(['id' => $id]);
    }
}
