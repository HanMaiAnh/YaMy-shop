// src/components/Header.jsx
import { Link } from "react-router-dom";
import "../index.css"; // đảm bảo import css toàn cục

export default function Header() {
  return (
    <header className="header">
      <div className="header-container">
        {/* LOGO - cột trái */}
        <div className="header-logo">
          <Link to="/">
            <img src="/images/logoyamy.png" alt="StreetSoul Logo" />
          </Link>
        </div>

        <nav className="nav-center" aria-label="Main navigation">
          <ul className="nav-menu">
            <li><Link to="/">Trang chủ</Link></li>
            <li><Link to="/products">Sản phẩm</Link></li>
            <li><Link to="/cart">Giỏ hàng</Link></li>
            <li><Link to="/orders">Đơn hàng</Link></li>
            <li><Link to="/login">Đăng nhập</Link></li>
          </ul>
        </nav>

        <div className="header-placeholder" />
      </div>
    </header>
  );
}
