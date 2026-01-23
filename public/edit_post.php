<?php
    session_start();
    require_once "../config/db.php";

    if(!isset($_SESSION['user_id'])){
        header("Location: login_user.php");
        exit;
    }

    $userId = $_SESSION['user_id'];
    $postId = $_GET['id'] ?? 0;

    if($postId <= 0){
        header("Location: my_posts.php");
        exit;
    }

    $stmt = $pdo->prepare("SELECT id, title, description FROM posts WHERE user_id = :uid AND id = :pid");
    $stmt->execute([
        ":uid" => $userId,
        ":pid" => $postId
    ]);

    $post = $stmt->fetch(PDO::FETCH_ASSOC);
    if(!$post){
        header("Location: my_posts.php");
        exit;
    }

    $stmt = $pdo->prepare("SELECT id, file_path FROM post_images WHERE post_id = :pid ORDER BY position ASC");
    $stmt->execute([":pid" => $postId]);

    $images = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $errors = $_SESSION['edit_post_errors'] ?? [];
    unset($_SESSION['edit_post_errors']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Post</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .image-card {
            height: 150px;
            background: #f8f9fa;
        }
        .image-thumb {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.25s ease;
        }
        .image-card:hover .image-thumb {
            transform: scale(1.05);
        }
        .image-actions {
            position: absolute;
            top: 6px;
            right: 6px;
            display: flex;
            gap: 6px;
        }
        .icon-btn {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            cursor: pointer;
            background: rgba(255,255,255,0.9);
            box-shadow: 0 2px 6px rgba(0,0,0,0.15);
            transition: all 0.2s ease;
        }
        .icon-edit {
            color: #0d6efd;
        }
        .icon-edit:hover {
            background: #0d6efd;
            color: #fff;
        }
        .icon-delete {
            color: #dc3545;
        }
        .icon-delete:hover {
            background: #dc3545;
            color: #fff;
        }
        .image-card.deleted {
            opacity: 0.4;
            pointer-events: none;
        }
        .image-card.replaced {
            outline: 3px solid #0d6efd;
        }

        .image-badge {
            position: absolute;
            bottom: 6px;
            left: 6px;
            background: #0d6efd;
            color: #fff;
            font-size: 11px;
            padding: 2px 6px;
            border-radius: 12px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
    </style>
</head>
<body>

<?php include "partials/navbar.php"; ?>

<div class="container-fluid">
    <div class="row min-vh-100">

        <?php include "partials/sidebar.php"; ?>

        <div class="col-md-10 p-4">
            <a href="view_post.php?id=<?= (int)$postId ?>" class="btn btn-dark btn-sm mb-3">← Back to Post</a>
            <div class="card shadow-sm">
                <div class="card-body">
                    <h4 class="mb-4">Edit Post</h4>
                    <form action="update_post.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="post_id" value="<?= (int)$postId ?>">
                        <div class="mb-3">
                            <label class="form-label">Title *</label>
                            <input id="title" type="text" name="title" class="form-control <?= isset($errors['title']) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($post['title']) ?>" required minlength="3" maxlength="150">
                            <div class="invalid-feedback" id="titleClientErr"><?= htmlspecialchars($errors['title'] ?? '') ?></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea id="description" name="description" class="form-control <?= isset($errors['description']) ? 'is-invalid' : '' ?>" maxlength="1000" rows="4"><?= htmlspecialchars($post['description']) ?></textarea>
                            <div class="invalid-feedback" id="descClientErr"><?= htmlspecialchars($errors['description'] ?? '') ?></div>

                            <h6 class="mb-2 mt-3">Existing Images</h6>
                            <?php if ($images): ?>
                                <div class="row g-3 mb-4">
                                <?php foreach ($images as $img): ?>
                                    <?php $imgId = (int)$img['id']; ?>
                                    <div class="col-6 col-md-3">
                                        <div class="image-card position-relative rounded overflow-hidden">
                                            <img src="../public/<?= htmlspecialchars($img['file_path']) ?>" class="image-thumb">
                                            <div class="image-actions">
                                                <label class="icon-btn icon-edit" title="Replace image">✏️
                                                    <input type="file" name="replace_image[<?= $imgId ?>]" accept=".jpg,.jpeg,.png" hidden onchange="markReplace(this)">
                                                </label>
                                                <button type="button" class="icon-btn icon-delete" onclick="markDelete(<?= $imgId ?>, this)">🗑️</button>
                                            </div>
                                            <div class="image-badge d-none">Replaced</div>
                                            <input type="hidden" name="delete_images[]" value="" id="delete-img-<?= $imgId ?>">
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                            <p class="text-muted">No images uploaded.</p>
                            <?php endif; ?>
                            <div class="mb-3">
                                <label class="form-label">Add New Images</label>
                                <?php if (!empty($errors['images'])): ?>
                                    <div class="alert alert-warning py-2 mb-2">
                                        <?= htmlspecialchars($errors['images']) ?>
                                    </div>
                                <?php endif; ?>
                                <div id="imagesClientError" class="alert alert-danger py-2 mb-2 d-none" role="alert"></div>
                                <input type="file" id="newImages" name="new_images[]" class="form-control" accept=".jpg,.jpeg,.png" multiple>
                                <small class="text-muted">Max 5 images in total</small>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-success">Save Changes</button>
                        <a href="view_post.php?id=<?= (int)$postId ?>" class="btn btn-dark">Cancel</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const ALLOWED_MIME = ['image/jpeg', 'image/png'];
    const MAX_SIZE = 2 * 1024 * 1024;
    const MAX_IMAGES = 5;

    const form = document.querySelector('form[action="update_post.php"]');
    const titleInput = document.getElementById('title');
    const descInput = document.getElementById('description');
    const titleClientErr = document.getElementById('titleClientErr');
    const descClientErr = document.getElementById('descClientErr');
    const newImagesInput = document.getElementById('newImages');
    const errorBox = document.getElementById('imagesClientError');
    const existingCount = <?= (int)count($images) ?>;

    function showError(msg) {
        if (!errorBox) return;
        errorBox.textContent = msg;
        errorBox.classList.remove('d-none');
    }

    function clearError() {
        if (!errorBox) return;
        errorBox.textContent = '';
        errorBox.classList.add('d-none');
    }

    function isValidImage(file) {
        if (!file) return true;

        if (file.size > MAX_SIZE) {
            showError('Each image must be 2MB or smaller.');
            return false;
        }

        if (file.type && !ALLOWED_MIME.includes(file.type)) {
            showError('Only JPG and PNG images are allowed.');
            return false;
        }

        return true;
    }

    function validateMaxImageCount(newCount = 0) {
        let deleteCount = 0;
        document.querySelectorAll('input[name="delete_images[]"]').forEach(input => {
            if (parseInt(input.value, 10) > 0) deleteCount++;
        });
        const total = (existingCount - deleteCount) + newCount;

        if (total > MAX_IMAGES) {
            showError('Maximum 5 images allowed. Delete some images first.');
            return false;
        }
        return true;
    }

    if (newImagesInput) {
        newImagesInput.addEventListener('change', () => {
            clearError();

            const files = newImagesInput.files;
            if (!files || !files.length) return;

            for (const file of files) {
                if (!isValidImage(file)) {
                    newImagesInput.value = '';
                    return;
                }
            }

            if (!validateMaxImageCount(files.length)) {
                newImagesInput.value = '';
            }
        });
    }

    document.querySelectorAll('input[type="file"][name^="replace_image["]')
        .forEach(input => {
            input.addEventListener('change', () => {
                clearError();
                const file = input.files[0];
                if (!isValidImage(file)) {
                    input.value = '';
                }
            });
        });

    function setErr(el, errEl, msg) {
        if (!el || !errEl) return true;
        errEl.textContent = msg || '';
        if (msg) el.classList.add('is-invalid');
        else el.classList.remove('is-invalid');
        return !msg;
    }

    function validateTitle() {
        const v = (titleInput?.value || '').trim();
        if (v.length === 0) {
            return setErr(titleInput, titleClientErr, 'Title is required.');
        }
        if (v.length < 3 || v.length > 150) {
            return setErr(titleInput, titleClientErr, 'Title must be 3–150 characters.');
        }
        return setErr(titleInput, titleClientErr, '');
    }

    function validateDescription() {
        const v = (descInput?.value || '').trim();
        if (v.length > 1000) {
            return setErr(descInput, descClientErr, 'Description cannot exceed 1000 characters.');
        }
        return setErr(descInput, descClientErr, '');
    }

    if (form) {
        form.addEventListener('submit', (e) => {
            clearError();

            const okTitle = validateTitle();
            const okDesc = validateDescription();
            if (!okTitle || !okDesc) {
                e.preventDefault();
                e.stopPropagation();
                if (!okTitle) titleInput?.focus();
                else descInput?.focus();
                return;
            }

            if (newImagesInput && newImagesInput.files.length) {
                for (const file of newImagesInput.files) {
                    if (!isValidImage(file)) {
                        e.preventDefault();
                        return;
                    }
                }
                if (!validateMaxImageCount(newImagesInput.files.length)) {
                    e.preventDefault();
                    return;
                }
            }
            
            const replaceInputs = document.querySelectorAll('input[type="file"][name^="replace_image["]');
            for (const input of replaceInputs) {
                if (input.files.length && !isValidImage(input.files[0])) {
                    e.preventDefault();
                    return;
                }
            }
        });
    }

    if (titleInput) titleInput.addEventListener('blur', validateTitle);
    if (descInput) descInput.addEventListener('blur', validateDescription);

    function markDelete(imgId, btn) {
        if (!confirm('Delete this image?')) return;
        document.getElementById('delete-img-' + imgId).value = imgId;

        const card = btn.closest('.image-card');
        card.classList.add('deleted');
    }

    function markReplace(input) {
        const file = input.files[0];
        if (!file) return;
        const card = input.closest('.image-card');
        const img  = card.querySelector('.image-thumb');
        const badge = card.querySelector('.image-badge');

        const reader = new FileReader();
        reader.onload = e => {
            img.src = e.target.result;
        };
        reader.readAsDataURL(file);
        card.classList.add('replaced');
        badge.classList.remove('d-none');
    }
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>