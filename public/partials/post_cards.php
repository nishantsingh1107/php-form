<div class="col-md-4">
    <div class="card h-100 shadow-sm position-relative <?= $post['admin_status'] === 'blocked' ? 'opacity-75' : '' ?>">
        <?php if ($post['thumbnail']): ?>
            <img src="../public/<?= htmlspecialchars($post['thumbnail']) ?>" class="card-img-top" style="height:200px;object-fit:cover;">
        <?php else: ?>
            <div class="bg-secondary text-white d-flex align-items-center justify-content-center" style="height:200px;">No Image</div>
        <?php endif; ?>

        <?php if ($post['admin_status'] === 'blocked'): ?>
            <div class="position-absolute top-0 end-0 m-2">
                <span class="badge bg-danger">Blocked by Admin</span>
            </div>
        <?php endif; ?>

        <div class="card-body">
            <h5><?= htmlspecialchars($post['title']) ?></h5>
            <?php if ($post['description']): ?>
                <p class="text-muted small">
                    <?= htmlspecialchars(mb_strimwidth($post['description'], 0, 120, '...')) ?>
                </p>
            <?php endif; ?>

            <div class="mt-2 mb-2">
                <span class="badge <?= $post['admin_status'] === 'approved' ? 'bg-success' : ($post['admin_status'] === 'blocked' ? 'bg-danger' : 'bg-warning text-dark') ?>">
                    Admin: <?= ucfirst($post['admin_status']) ?>
                </span>
            </div>
            <a href="view_post.php?id=<?= (int)$post['id'] ?>" class="stretched-link" aria-label="View post"></a>
        </div>
        <div class="card-footer bg-white d-flex justify-content-between position-relative" style="z-index:2;">
            <form method="post" action="toggle_post_status.php" onsubmit="return confirm('<?= $post['status'] === 'public' ? 'Are you sure you want to hide this post?' : 'Are you sure you want to make this post public?' ?>');">
                <input type="hidden" name="post_id" value="<?= (int)$post['id'] ?>">
                <button 
                    class="btn btn-sm <?= $post['status'] === 'public' ? 'btn-warning' : 'btn-success' ?>" 
                    <?= $post['admin_status'] === 'blocked' ? 'disabled' : '' ?>>
                    <?= $post['status'] === 'public' ? 'Hide' : 'Public' ?>
                </button>
            </form>

            <form method="post" action="delete_post.php" onsubmit="return confirm('Delete this post permanently?');">
                <input type="hidden" name="post_id" value="<?= (int)$post['id'] ?>">
                <button class="btn btn-sm btn-danger" <?= $post['admin_status'] === 'blocked' ? 'disabled' : '' ?>>Delete</button>
            </form>
        </div>

    </div>
</div>
