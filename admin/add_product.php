<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') header('Location: ../login.php');

include '../config/db.php';
include '../includes/header.php';

if (isset($_POST['add'])) {
    $name = $_POST['name'];
    $desc = $_POST['description'];
    $price = $_POST['price'];
    $category_id = $_POST['category_id'];
    $stock = $_POST['stock'];

    // Upload hình ảnh
    $image = 'images/' . basename($_FILES['image']['name']);
    move_uploaded_file($_FILES['image']['tmp_name'], '../' . $image);

    $stmt = $pdo->prepare("INSERT INTO products (name, description, price, image, category_id, stock) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$name, $desc, $price, $image, $category_id, $stock]);
    header('Location: products.php');
}

// Lấy danh mục
$stmt = $pdo->query("SELECT * FROM categories");
$categories = $stmt->fetchAll();
?>

<h1>Thêm sản phẩm</h1>
<form method="POST" enctype="multipart/form-data">
    <div class="mb-3"><input type="text" name="name" class="form-control" placeholder="Tên sản phẩm" required></div>
    <div class="mb-3"><textarea name="description" class="form-control" placeholder="Mô tả"></textarea></div>
    <div class="mb-3"><input type="number" name="price" class="form-control" placeholder="Giá" required></div>
    <div class="mb-3">
        <select name="category_id" class="form-control">
            <?php foreach ($categories as $cat): ?>
                <option value="<?php echo $cat['id']; ?>"><?php echo $cat['name']; ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="mb-3"><input type="number" name="stock" class="form-control" placeholder="Số lượng tồn" required></div>
    <div class="mb-3"><input type="file" name="image" class="form-control" required></div>
    <button type="submit" name="add" class="btn btn-primary">Thêm</button>
</form>

<?php include '../includes/footer.php'; ?>