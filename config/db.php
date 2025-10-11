<?php
class Database {
    private $host = "localhost";     // Tên host, thường là localhost
    private $username = "root";      // Tên user MySQL (mặc định: root)
    private $password = "";          // Mật khẩu MySQL (mặc định trống)
    private $dbname = "streetsoul_store999";  // Tên database của bạn
    private $conn;

    public function connect() {
        $this->conn = null;

        try {
            $this->conn = new mysqli($this->host, $this->username, $this->password, $this->dbname);

            if ($this->conn->connect_error) {
                die("Kết nối thất bại: " . $this->conn->connect_error);
            }

            // Thiết lập UTF-8 để tránh lỗi tiếng Việt
            $this->conn->set_charset("utf8mb4");
        } catch (Exception $e) {
            echo "Lỗi kết nối: " . $e->getMessage();
        }

        return $this->conn;
    }
}
?>
