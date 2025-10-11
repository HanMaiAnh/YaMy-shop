import ProductCard from "../components/ProductCard";
import { products } from "../data/products";
import "./Products.css";

export default function Products() {
  return (
    <div style={{ padding: "20px" }}>
      <h2 style={{ textAlign: "center", marginBottom: "20px" }}>
        Sản phẩm của chúng tôi
      </h2>

      <div className="products-grid">
        {products.map((p) => (
          <ProductCard key={p.id} p={p} />
        ))}
      </div>
    </div>
  );
}
