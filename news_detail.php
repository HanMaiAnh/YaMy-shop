<?php
session_start();

require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';

// Lấy id tin từ URL
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    die('Tin tức không hợp lệ.');
}

// Lấy tin chính
$stmt = $pdo->prepare("SELECT * FROM news WHERE id = :id");
$stmt->execute(['id' => $id]);
$news = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$news) {
    die('Không tìm thấy tin tức.');
}

// Lấy tin liên quan (3 tin khác, mới nhất)
$stmtRel = $pdo->prepare("
    SELECT * FROM news 
    WHERE id <> :id 
    ORDER BY created_at DESC 
    LIMIT 3
");
$stmtRel->execute(['id' => $id]);
$relatedNews = $stmtRel->fetchAll(PDO::FETCH_ASSOC);

include '../includes/header.php';
?>

<style>
    .news-detail-wrapper {
        background: #f7f7f9;
        padding: 40px 0 60px;
    }
    .news-detail-title {
        font-size: 2rem;
        font-weight: 700;
        margin-bottom: 10px;
    }
    .news-detail-meta {
        color: #777;
        font-size: .9rem;
        margin-bottom: 20px;
    }
    .news-detail-img {
        width: 100%;
        max-height: 380px;
        object-fit: cover;
        border-radius: 14px;
        margin-bottom: 25px;
    }
    .news-detail-content {
        font-size: 1rem;
        line-height: 1.7;
        color: #333;
        background: #fff;
        padding: 22px 24px;
        border-radius: 16px;
        box-shadow: 0 8px 20px rgba(0,0,0,.04);
    }
    .news-related-title {
        font-size: 1.3rem;
        font-weight: 600;
        margin-bottom: 16px;
    }
    .related-card {
        background: #fff;
        border-radius: 14px;
        overflow: hidden;
        box-shadow: 0 6px 18px rgba(0,0,0,.05);
        transition: transform .2s ease, box-shadow .2s ease;
        display: flex;
        flex-direction: column;
        height: 100%;
    }
    .related-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 30px rgba(0,0,0,.08);
    }
    .related-img-wrap {
        width: 100%;
        height: 160px;
        overflow: hidden;
    }
    .related-img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }
    .related-body {
        padding: 12px 14px 16px;
        display: flex;
        flex-direction: column;
        height: 100%;
    }
    .related-body h3 {
        font-size: .95rem;
        font-weight: 600;
        margin-bottom: 6px;
    }
    .related-body p {
        font-size: .85rem;
        color: #555;
        margin-bottom: 8px;
        flex-grow: 1;
    }
    .related-date {
        font-size: .8rem;
        color: #999;
    }
</style>

<div class="news-detail-wrapper">
    <div class="container">
        <div class="row">
            <!-- Nội dung chính -->
            <div class="col-lg-8 mx-auto mb-4">
                <h1 class="news-detail-title">
                    <?= htmlspecialchars($news['title']) ?>
                </h1>

                <div class="news-detail-meta">
                    Ngày đăng: <?= date('d/m/Y H:i', strtotime($news['created_at'])) ?>
                </div>

                <?php if (!empty($news['image'])): ?>
                    <img src="<?= htmlspecialchars($news['image']) ?>"
                         alt="<?= htmlspecialchars($news['title']) ?>"
                         class="news-detail-img">
                <?php endif; ?>

                <div class="news-detail-content">
                    <?= nl2br($news['content']) ?>
                </div>
            </div>
        </div>

        <!-- Tin liên quan -->
        <?php if (!empty($relatedNews)): ?>
            <div class="row mt-4">
                <div class="col-12">
                    <h2 class="news-related-title">Tin tức liên quan</h2>
                </div>

                <?php foreach ($relatedNews as $item): ?>
                    <div class="col-md-4 mb-3">
                        <article class="related-card">
                            <?php if (!empty($item['image'])): ?>
                                <div class="related-img-wrap">
                                    <img src="<?= htmlspecialchars($item['image']) ?>"
                                         class="related-img"
                                         alt="<?= htmlspecialchars($item['title']) ?>"
                                         loading="lazy">
                                </div>
                            <?php endif; ?>

                            <div class="related-body">
                                <h3>
                                    <a href="news_detail.php?id=<?= $item['id'] ?>" style="text-decoration:none;color:inherit;">
                                        <?= htmlspecialchars($item['title']) ?>
                                    </a>
                                </h3>
                                <p>
                                    <?= mb_substr(strip_tags($item['content']), 0, 80) . '...' ?>
                                </p>
                                <span class="related-date">
                                    <?= date('d/m/Y', strtotime($item['created_at'])) ?>
                                </span>
                            </div>
                        </article>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>
</div>

<?php include '../includes/footer.php'; ?>
