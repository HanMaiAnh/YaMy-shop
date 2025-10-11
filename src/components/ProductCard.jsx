import { Link } from "react-router-dom";
import "../pages/Products.css";

export default function ProductCard({ p }) {
  return (
    <div className="product-card">
      <div className="product-image-wrap">
        <img className="product-image" src={p.image} alt={p.name} />
      </div>

      <div className="product-content">
        <h4 className="product-title">{p.name}</h4>
        <p className="product-price">{Number(p.price).toLocaleString()} ₫</p>
        <p className="product-desc">{p.description}</p>
      </div>

      <div className="product-actions">
        <Link className="product-link" to={`/products/${p.id}`}>
          Xem chi tiết
        </Link>
        <button className="add-btn">Thêm vào giỏ hàng</button>
      </div>
    </div>
  );
}
