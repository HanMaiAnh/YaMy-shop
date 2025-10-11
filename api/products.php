<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/../config/db.php';

$db = new Database();
$conn = $db->connect();

$sql = "SELECT id, name, price, description, image FROM products ORDER BY id DESC";
$result = $conn->query($sql);

$products = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $products[] = [
            "id" => (int)$row["id"],
            "name" => $row["name"],
            "price" => (int)$row["price"],
            "description" => $row["description"],
            "image" => "http://localhost/streetsoul_spa/images/" . $row["image"]
        ];
    }
}

echo json_encode($products, JSON_UNESCAPED_UNICODE);
$conn->close();
?>
