import { useParams, Link } from "react-router-dom";
import { products } from "../data/products";
import "./ProductDetail.css";

export default function ProductDetail() {
  const { id } = useParams();
  const product = products.find((p) => p.id === parseInt(id));

  if (!product) {
    return (
      <div className="not-found">
        <h2>Không tìm thấy sản phẩm</h2>
        <Link to="/products" className="back-link">Quay lại</Link>
      </div>
    );
  }

  return (
    <div className="product-detail">
      <img src={product.image} alt={product.name} className="product-image" />

      <div className="product-info">
        <h2>{product.name}</h2>
        <p className="product-price">{product.price.toLocaleString()} ₫</p>
        <p className="product-desc">{product.description}</p>

        <Link to="/cart" className="add-to-cart-btn">
          Thêm vào giỏ hàng
        </Link>
      </div>
    </div>
  );
}
