import { useEffect, useState } from "react";
import { Link } from "react-router-dom";
import "../index.css";

export default function Home() {
  const [products, setProducts] = useState([]);

  useEffect(() => {
    fetch("http://localhost/streetsoul_spa/api/products.php")
      .then((res) => res.json())
      .then((data) => setProducts(data))
      .catch((err) => console.error("Lỗi tải sản phẩm:", err));
  }, []);

  const featured = products.slice(0, 4);
  const discounted = products.filter(p => p.discount_price && p.discount_price < p.price).slice(0, 4);

  return (
    <main>
      <h2 className="home-title">Sản phẩm nổi bật</h2>
      <div className="product-list">
        {featured.map((product) => (
          <div key={product.id} className="product-card">
            <img src={product.image} alt={product.name} className="product-image" />
            <h3 className="product-name">{product.name}</h3>
            <p className="product-price">{product.price.toLocaleString()} ₫</p>
            <p className="product-desc">{product.description}</p>
            <div className="product-buttons">
              <Link to={`/products/${product.id}`} className="detail-link">Xem chi tiết</Link>
              <Link to="/cart" className="cart-button">Thêm vào giỏ hàng</Link>
            </div>
          </div>
        ))}
      </div>

      <h2 className="home-title">Sản phẩm giảm giá</h2>
      <div className="product-list">
        {discounted.map((product) => (
          <div key={product.id} className="product-card">
            <img src={product.image} alt={product.name} className="product-image" />
            <h3 className="product-name">{product.name}</h3>
            <p>
              <span style={{ textDecoration: "line-through", color: "#888" }}>
                {product.price.toLocaleString()} ₫
              </span>{" "}
              <span style={{ color: "#ff4b4b", fontWeight: "700" }}>
                {product.discount_price.toLocaleString()} ₫
              </span>
            </p>
            <p className="product-desc">{product.description}</p>
            <div className="product-buttons">
              <Link to={`/products/${product.id}`} className="detail-link">Xem chi tiết</Link>
              <Link to="/cart" className="cart-button">Thêm vào giỏ hàng</Link>
            </div>
          </div>
        ))}
      </div>
    </main>
  );
}
