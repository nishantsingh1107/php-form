# Using Database Relationships (PK/FK/Unique/Cascade) + Joins + Search/Sort + Indexing in This Project

## Goal
Apply these DB concepts in a real feature inside your PHP project:

**Feature idea:** A user can create a **Post** and upload **multiple images** for that post. Then you can list posts, show images, search, sort, and display grouped data.

This feature naturally uses:
- **Primary keys** (PK)
- **Foreign keys** (FK)
- **Unique constraints**
- **Cascading** deletes/updates
- **Joins**
- **Searching & sorting**
- **Indexing**
- **Grouped data** (`GROUP BY`, `COUNT`, etc.)

---

## 1) Core Relationship Design

### Entities (tables)
- `users` (already in your project)
- `posts` (new)
- `post_images` (new)

### Relationships
- **One user → many posts**
  - `posts.user_id` references `users.id`
- **One post → many images**
  - `post_images.post_id` references `posts.id`

### Why these constraints matter
- **PK**: uniquely identifies each row.
- **FK**: prevents orphan records (e.g., an image row without an existing post).
- **UNIQUE**: prevents duplicates and keeps ordering consistent.
- **CASCADE**: when a parent row is deleted, related child rows are removed automatically.

> Note: DB cascades delete rows in MySQL, but they do **not** delete image files on disk. File deletion must be done in PHP.

---

## 2) Suggested MySQL Schema (PK/FK/Unique/Cascade + Indexes)

> This assumes you already have a `users` table with an `id` PK.

### `posts` table
```sql
CREATE TABLE posts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(150) NOT NULL,
  body TEXT NULL,
  slug VARCHAR(180) NOT NULL,
  status ENUM('draft','published') NOT NULL DEFAULT 'published',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

  CONSTRAINT fk_posts_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,

  UNIQUE KEY uq_posts_slug (slug),

  -- Indexes for common listing patterns (fast filtering + sorting)
  KEY idx_posts_user_created (user_id, created_at),
  KEY idx_posts_status_created (status, created_at)
);
```

### `post_images` table
```sql
CREATE TABLE post_images (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  post_id BIGINT UNSIGNED NOT NULL,
  file_name VARCHAR(255) NOT NULL,
  file_path VARCHAR(500) NOT NULL,     -- e.g. "uploads/posts/12/55/img_xxx.jpg"
  mime VARCHAR(50) NOT NULL,           -- e.g. "image/jpeg"
  size_bytes INT UNSIGNED NOT NULL,
  position INT UNSIGNED NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  CONSTRAINT fk_post_images_post
    FOREIGN KEY (post_id) REFERENCES posts(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,

  -- Ensures image order is unique per post
  UNIQUE KEY uq_post_position (post_id, position),

  -- Index for fast joins
  KEY idx_post_images_post (post_id)
);
```

---

## 3) Feature Flow (How You’d Build It)

### A) Create Post (UI)
1. User opens a page like `public/create_post.php`.
2. Form includes:
   - `title`, `body`
   - `<input type="file" name="post_images[]" multiple>`
3. Submit to `public/store_post.php`.

### B) Store Post + Upload Multiple Images (Server)
Use a **transaction** so DB changes are consistent.

**High-level steps:**
1. Check session (ensure user is logged in).
2. Validate `title/body`.
3. `BEGIN` transaction.
4. Insert row into `posts` → get `$postId`.
5. Loop over each uploaded file:
   - Validate upload errors.
   - Validate file type (`jpg/png/webp`), size, and real image (`getimagesize`).
   - Move file to `public/uploads/posts/{userId}/{postId}/...`.
   - Insert row into `post_images` with `post_id` and `position`.
6. `COMMIT` transaction.
7. Redirect to `view_post.php?post={id}`.

**Failure handling (important):**
- If any file move/insert fails → `ROLLBACK`.
- Also delete any files that were already moved.

### C) Delete Post (Cascade + File Cleanup)
- DB cascade will remove rows in `post_images` automatically when a `posts` row is deleted.
- But in PHP you must remove physical files from `uploads/posts/{userId}/{postId}/`.

---

## 4) Queries That Prove You Understand Joins + Grouped Data

### A) List a user’s posts with image count + thumbnail (JOIN + GROUP BY)
```sql
SELECT
  p.id,
  p.title,
  p.slug,
  p.created_at,
  COUNT(pi.id) AS image_count,
  MIN(pi.file_path) AS thumbnail
FROM posts p
LEFT JOIN post_images pi ON pi.post_id = p.id
WHERE p.user_id = :uid
GROUP BY p.id
ORDER BY p.created_at DESC
LIMIT :offset, :limit;
```

**What this demonstrates:**
- `LEFT JOIN` keeps posts even if they have 0 images.
- `GROUP BY` groups images per post.
- `COUNT()` gives image totals.
- `MIN()` can be used to pick a simple “thumbnail” path.

### B) View a single post with all its images (JOIN + ordering)
```sql
SELECT
  p.id,
  p.title,
  p.body,
  p.created_at,
  pi.file_path,
  pi.position
FROM posts p
LEFT JOIN post_images pi ON pi.post_id = p.id
WHERE p.id = :postId AND p.user_id = :uid
ORDER BY pi.position ASC;
```

---

## 5) Searching + Sorting (and how indexing helps)

### Simple search (LIKE)
```sql
SELECT p.*
FROM posts p
WHERE p.user_id = :uid
  AND (p.title LIKE :q OR p.body LIKE :q)
ORDER BY p.created_at DESC;
```

**Indexing notes:**
- Searching with `LIKE '%term%'` won’t fully benefit from a normal index.
- For real projects, use **FULLTEXT** for text search:

```sql
ALTER TABLE posts ADD FULLTEXT KEY ft_posts_title_body (title, body);

SELECT p.*
FROM posts p
WHERE p.user_id = :uid
  AND MATCH(p.title, p.body) AGAINST (:q IN NATURAL LANGUAGE MODE)
ORDER BY p.created_at DESC;
```

### Sorting examples
- newest first: `ORDER BY created_at DESC`
- oldest first: `ORDER BY created_at ASC`
- title: `ORDER BY title ASC`

Use indexes like `(user_id, created_at)` to make **filtering + sorting** fast.

---

## 6) Minimal Pages/Endpoints to Implement

Suggested new pages (keep consistent with your current structure):
- `public/create_post.php` — HTML form
- `public/store_post.php` — insert + upload multiple files
- `public/my_posts.php` — list user posts (search + sort + pagination)
- `public/view_post.php` — show post + images
- (optional) `public/delete_post.php` — delete post + delete files

---

## 7) How This Fits Your Current Project

You already have patterns for:
- **PDO DB access** (`config/db.php`)
- **Session-based auth** (`public/user_dashboard.php`, `public/login_user.php`)
- **File upload validation** (`public/update_profile.php`)

So the “post + multiple images” feature is the best place to demonstrate:
- relationships + constraints (PK/FK/Unique/Cascade)
- joins + grouped data
- searching/sorting
- indexing strategy

---

## Next Step
If you want, the next implementation step is to create `public/store_post.php` using:
- `$pdo->beginTransaction()` / `$pdo->commit()`
- loop through `$_FILES['post_images']`
- move files into `public/uploads/posts/{userId}/{postId}/`
- insert rows into `post_images`
