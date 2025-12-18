<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') header('Location: ../login.php');

include '../config/db.php';
include '../includes/header.php';

$id = (int)$_GET['id'];
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$id]);
$product = $stmt->fetch();

if (isset($_POST['edit'])) {
    $name = $_POST['name'];
    $desc = $_POST['description'];
    $price = $_POST['price'];
    $category_id = $_POST['category_id'];
    $stock = $_POST['stock'];

    $image = $product['image'];
    if ($_FILES['image']['name']) {
        $image = 'images/' . basename($_FILES['image']['name']);
        move_uploaded_file($_FILES['image']['tmp_name'], '../' . $image);
    }

    $stmt = $pdo->prepare("UPDATE products SET name=?, description=?, price=?, image=?, category_id=?, stock=? WHERE id=?");
    $stmt->execute([$name, $desc, $price, $image, $category_id, $stock, $id]);
    header('Location: products.php');
}

// Lấy danh mục
$stmt = $pdo->query("SELECT * FROM categories");
$categories = $stmt->fetchAll();
?>

<h1>Sửa sản phẩm</h1>
<form method="POST" enctype="multipart/form-data">
    <div class="mb-3"><input type="text" name="name" class="form-control" value="<?php echo $product['name']; ?>" required></div>
    <div class="mb-3"><textarea name="description" class="form-control"><?php echo $product['description']; ?></textarea></div>
    <div class="mb-3"><input type="number" name="price" class="form-control" value="<?php echo $product['price']; ?>" required></div>
    <div class="mb-3">
        <select name="category_id" class="form-control">
            <?php foreach ($categories as $cat): ?>
                <option value="<?php echo $cat['id']; ?>" <?php echo $cat['id'] == $product['category_id'] ? 'selected' : ''; ?>><?php echo $cat['name']; ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="mb-3"><input type="number" name="stock" class="form-control" value="<?php echo $product['stock']; ?>" required></div>
    <div class="mb-3"><input type="file" name="image" class="form-control"> (Hiện tại: <?php echo $product['image']; ?>)</div>
    <button type="submit" name="edit" class="btn btn-primary">Cập nhật</button>
</form>

<?php include '../includes/footer.php'; ?>