<?php
    session_start();
    if (!isset($_SESSION['user_id'])) {
        header("Location: login_user.php");
        exit;
    }

    $postErrors = $_SESSION['post_errors'] ?? [];
    $old = $_SESSION['old_post'] ?? [];
    unset($_SESSION['post_errors'], $_SESSION['old_post']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create Post</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php include "partials/navbar.php"; ?>
<div class="container-fluid">
    <div class="row min-vh-100">
        <?php include "partials/sidebar.php"; ?>
        <div class="col-md-10 p-4">
            <h4 class="mb-4">Create a Post</h4>
            <?php if (isset($postErrors['general'])): ?>
                <div class="alert alert-danger">
                    <?= htmlspecialchars($postErrors['general']) ?>
                </div>
            <?php endif; ?>

            <form id="createPostForm" action="store_post.php" method="POST" enctype="multipart/form-data">
                <div class="mb-3">
                    <label class="form-label">Title<span class="text-danger">*</span></label>
                    <input type="text" id="title" name="title" class="form-control <?= isset($postErrors['title']) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($old['title'] ?? '') ?>" required minlength="3" maxlength="150">
                    <div id="titleErr" class="invalid-feedback">
                        <?= htmlspecialchars($postErrors['title'] ?? '') ?>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Description</label>
                    <textarea id="description" name="description" rows="3" maxlength="1000" class="form-control <?= isset($postErrors['description']) ? 'is-invalid' : '' ?>"><?= htmlspecialchars($old['description'] ?? '') ?></textarea>
                    <div id="descErr" class="invalid-feedback">
                        <?= htmlspecialchars($postErrors['description'] ?? '') ?>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Images<span class="text-danger">*</span></label>
                    <input type="file" id="postImages" name="post_images[]" class="form-control <?= isset($postErrors['post_images']) ? 'is-invalid' : '' ?>" accept=".jpg,.jpeg,.png,image/jpeg,image/png" multiple required>
                    <div id="imgErr" class="invalid-feedback d-block">
                        <?= htmlspecialchars($postErrors['post_images'] ?? '') ?>
                    </div>
                    <div id="preview" class="row g-2 mt-2"></div>
                </div>
                <button type="submit" class="btn btn-success">Upload Post</button>
            </form>
        </div>
    </div>
</div>
<script>
    const titleInput = document.getElementById('title');
    const descInput = document.getElementById('description');
    const imgInput = document.getElementById('postImages');
    const titleErr = document.getElementById('titleErr');
    const descErr = document.getElementById('descErr');
    const imgErr = document.getElementById('imgErr');

    function setErr(el, errEl, msg){
        errEl.textContent = msg || '';
        if(msg) el.classList.add('is-invalid');
        else el.classList.remove('is-invalid');
        return !msg;
    }
    
    function validateTitle(){
        const v = titleInput.value.trim();
        if(v === ''){
            return setErr(titleInput, titleErr, 'Title cannot be only spaces.');
        }
        if(v.length < 3 || v.length > 150){
            return setErr(titleInput, titleErr, 'Title must be 3–150 characters.');
        }
        return setErr(titleInput, titleErr, '');
    }

    function validateDescription(){
        if(descInput.value.length > 1000){
            return setErr(descInput, descErr, 'Description too long.');
        }
        return setErr(descInput, descErr, '');
    }

    function validateImages(){
        const files = imgInput.files;
        if(!files || files.length === 0){
            return setErr(imgInput, imgErr, 'At least one image is required.');
        }
        if(files.length > 5){
            return setErr(imgInput, imgErr, 'Maximum 5 images allowed.');
        }

        for(const f of files){
            if(f.size > 2 * 1024 * 1024){
                return setErr(imgInput, imgErr, 'Each image must be under 2MB.');
            }
            if(!['image/jpeg','image/png'].includes(f.type)){
                return setErr(imgInput, imgErr, 'Invalid image type.');
            }
        }
        return setErr(imgInput, imgErr, '');
    }

    imgInput.addEventListener('change', e => {
        validateImages();
        const preview = document.getElementById('preview');
        preview.innerHTML = '';
        Array.from(e.target.files).forEach(file => {
            const reader = new FileReader();
            reader.onload = ev => {
                const col = document.createElement('div');
                col.className = 'col-md-3';
                col.innerHTML = `<img src="${ev.target.result}" class="img-fluid rounded" style="height:180px;object-fit:cover;">`;
                preview.appendChild(col);
            };
            reader.readAsDataURL(file);
        });
    });

    titleInput.addEventListener('blur', validateTitle);
    descInput.addEventListener('blur', validateDescription);

    document.getElementById('createPostForm').addEventListener('submit', e => {
        const ok =
            validateTitle() &
            validateDescription() &
            validateImages();

        if(!ok){
            e.preventDefault();
            e.stopPropagation();
        }
    });
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
