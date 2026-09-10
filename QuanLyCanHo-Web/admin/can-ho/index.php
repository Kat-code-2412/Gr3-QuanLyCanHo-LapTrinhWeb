<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../auth/guard.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/module2_helpers.php';
requireAdmin();
$title = 'Quản lý căn hộ';
$keyword = trim((string)($_GET['keyword'] ?? '')); $maLoai = (int)($_GET['MaLoai'] ?? 0); $trangThai = (string)($_GET['TrangThai'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1)); $perPage = (int)($_GET['perPage'] ?? 10); $perPage = in_array($perPage, [10,20,50,100], true) ? $perPage : 10; $offset = ($page - 1) * $perPage;
$where = []; $params = [];
if ($keyword !== '') { $where[] = '(c.SoPhong LIKE :kw1 OR CAST(c.MaCanHo AS CHAR) LIKE :kw2)'; $params[':kw1'] = "%$keyword%"; $params[':kw2'] = "%$keyword%"; }
if ($maLoai > 0) { $where[] = 'c.MaLoai = :loai'; $params[':loai'] = $maLoai; }
if (in_array($trangThai, ['Trống','Đang thuê','Bảo trì'], true)) { $where[] = 'c.TrangThai = :tt'; $params[':tt'] = $trangThai; }
$ws = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$stmt = $pdo->prepare("SELECT COUNT(*) FROM CanHo c $ws"); $stmt->execute($params); $total = (int)$stmt->fetchColumn(); $totalPages = max(1, (int)ceil($total / $perPage));
$stmt = $pdo->prepare("SELECT c.*, l.TenLoai, (SELECT COUNT(*) FROM CanHo_Anh a WHERE a.MaCanHo=c.MaCanHo) SoAnh, (SELECT a.DuongDan FROM CanHo_Anh a WHERE a.MaCanHo=c.MaCanHo AND a.LaAnhDaiDien=1 ORDER BY a.MaAnh LIMIT 1) AnhDaiDien FROM CanHo c JOIN LoaiCanHo l ON l.MaLoai=c.MaLoai $ws ORDER BY c.MaCanHo DESC LIMIT :limit OFFSET :offset");
foreach ($params as $k => $v) $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR); $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT); $stmt->bindValue(':offset', $offset, PDO::PARAM_INT); $stmt->execute(); $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
$types = $pdo->query('SELECT MaLoai, TenLoai FROM LoaiCanHo ORDER BY TenLoai')->fetchAll(PDO::FETCH_ASSOC);
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="page-header"><div><h1>Căn hộ</h1><p class="text-muted">Danh sách căn hộ, trạng thái, loại và ảnh.</p></div><div class="actions"><a class="btn btn-secondary" href="../loai-can-ho/index.php">Loại căn</a><a class="btn btn-primary" href="create.php">+ Thêm căn</a></div></div>
<form method="get" class="filter-card"><div class="form-group grow"><label>Tìm mã/số phòng</label><input name="keyword" value="<?=e($keyword)?>" placeholder="VD: 101"></div><div class="form-group"><label>Loại căn</label><select name="MaLoai"><option value="0">Tất cả</option><?php foreach($types as $t):?><option value="<?=$t['MaLoai']?>" <?=$maLoai===(int)$t['MaLoai']?'selected':''?>><?=e($t['TenLoai'])?></option><?php endforeach;?></select></div><div class="form-group"><label>Trạng thái</label><select name="TrangThai"><option value="">Tất cả</option><?php foreach(['Trống','Đang thuê','Bảo trì'] as $s):?><option value="<?=e($s)?>" <?=$trangThai===$s?'selected':''?>><?=e($s)?></option><?php endforeach;?></select></div><div class="form-group"><label>Số dòng</label><select name="perPage"><?php foreach([10,20,50,100] as $n):?><option value="<?=$n?>" <?=$perPage===$n?'selected':''?>><?=$n?></option><?php endforeach;?></select></div><button class="btn btn-secondary">Lọc</button><a class="btn btn-light" href="index.php">Xóa</a></form>
<div class="table-card"><div class="table-responsive"><table class="data-table"><thead><tr><th>Ảnh</th><th>Mã</th><th>Số phòng</th><th>Loại</th><th>Diện tích</th><th>Giá thuê</th><th>Trạng thái</th><th>Ảnh</th><th>Thao tác</th></tr></thead><tbody>
<?php if (!$items): ?>
	<tr><td colspan="9" class="empty-state">Không có căn hộ phù hợp.</td></tr>
<?php else: ?>
	<?php foreach ($items as $item): ?>
		<tr>
			<td><?php if ($item['AnhDaiDien']): ?><img class="thumb" src="<?= e(appUrl($item['AnhDaiDien'])) ?>" alt="Ảnh căn <?= e($item['SoPhong']) ?>"><?php else: ?><div class="thumb no-image">🏢</div><?php endif; ?></td>
			<td>#<?= (int)$item['MaCanHo'] ?></td>
			<td><strong><?= e($item['SoPhong']) ?></strong></td>
			<td><?= e($item['TenLoai']) ?></td>
			<td><?= number_format((float)$item['DienTich'], 2, ',', '.') ?> m²</td>
			<td><?= formatMoney($item['GiaThue']) ?></td>
			<td><?= renderStatusBadge($item['TrangThai']) ?></td>
			<td><?= (int)$item['SoAnh'] ?> ảnh</td>
			<td><div class="actions"><a class="btn btn-sm btn-light" href="detail.php?id=<?= (int)$item['MaCanHo'] ?>">Xem</a><a class="btn btn-sm btn-secondary" href="edit.php?id=<?= (int)$item['MaCanHo'] ?>">Sửa</a><form method="post" action="delete.php" class="inline-form" onsubmit="return confirm('Bạn có chắc muốn xóa căn hộ này? Chỉ căn hộ chưa có hợp đồng hoặc yêu cầu bảo trì mới được xóa.');"><input type="hidden" name="csrf_token" value="<?= e(module2_csrf_token()) ?>"><input type="hidden" name="id" value="<?= (int)$item['MaCanHo'] ?>"><button class="btn btn-sm btn-danger" type="submit">Xóa</button></form></div></td>
		</tr>
	<?php endforeach; ?>
<?php endif; ?>
</tbody></table></div></div>
<?=module2_pagination($page,$totalPages,['keyword'=>$keyword,'MaLoai'=>$maLoai,'TrangThai'=>$trangThai,'perPage'=>$perPage])?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>