import { Routes, Route, Link } from "react-router-dom";
import Home from "./pages/Home";
import Products from "./pages/Products";
import ProductDetail from "./pages/ProductDetail";
import Cart from "./pages/Cart";
import Order from "./pages/Order";
import Login from "./pages/Login";

export default function App() {
  return (
    <>
      <header style={{ background: "#000", padding: "10px" }}>
        <nav style={{ display: "flex", gap: "20px" }}>
          <Link to="/" style={{ color: "white", textDecoration: "none" }}>Trang chủ</Link>
          <Link to="/products" style={{ color: "white", textDecoration: "none" }}>Sản phẩm</Link>
          <Link to="/cart" style={{ color: "white", textDecoration: "none" }}>Giỏ hàng</Link>
          <Link to="/orders" style={{ color: "white", textDecoration: "none" }}>Đơn hàng</Link>
          <Link to="/login" style={{ color: "white", textDecoration: "none" }}>Đăng nhập</Link>
        </nav>
      </header>

      <main style={{ minHeight: "80vh", padding: "20px" }}>
        <Routes>
          <Route path="/" element={<Home />} />
          <Route path="/products" element={<Products />} />
          <Route path="/products/:id" element={<ProductDetail />} />
          <Route path="/cart" element={<Cart />} />
          <Route path="/orders" element={<Order />} />
          <Route path="/login" element={<Login />} />
        </Routes>
      </main>

      <footer style={{
        background: "#000",
        color: "white",
        textAlign: "center",
        padding: "10px 0"
      }}>
        © 2025 StreetSoul Store. All rights reserved.
      </footer>
    </>
  );
}
