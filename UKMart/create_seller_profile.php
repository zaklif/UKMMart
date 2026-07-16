<?php
session_start();
include('db_connect.php');

// Make sure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $seller_name = trim($_POST['seller_name']);
    $seller_description = trim($_POST['seller_description']);

    // Insert new seller profile WITHOUT picture first
    $stmt = $conn->prepare("INSERT INTO tbl_sellers_ukmart (fld_user_id, fld_seller_name, fld_seller_description, fld_seller_rating) VALUES (:uid, :name, :desc, 0)");
    $stmt->bindParam(':uid', $user_id);
    $stmt->bindParam(':name', $seller_name);
    $stmt->bindParam(':desc', $seller_description);
    $stmt->execute();

    // Get the new seller ID
    $seller_id = $conn->lastInsertId();
    $profile_file_name = null;

    // Handle profile picture upload
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
        $fileTmp = $_FILES['profile_pic']['tmp_name'];
        $fileName = $_FILES['profile_pic']['name'];
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png'];

        if (in_array($ext, $allowed)) {
            if (!is_dir("profile")) mkdir("profile", 0755, true);
            $profile_file_name = $seller_id . "." . $ext; // e.g., 12.png
            move_uploaded_file($fileTmp, "profile/" . $profile_file_name);

            // Update the seller record with the profile picture filename
            $update_stmt = $conn->prepare("UPDATE tbl_sellers_ukmart SET fld_profile_pic = :pic WHERE fld_seller_id = :sid");
            $update_stmt->bindParam(':pic', $profile_file_name);
            $update_stmt->bindParam(':sid', $seller_id);
            $update_stmt->execute();
        }
    }

    // Redirect to seller page
    header("Location: seller.php");
    exit();
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Create Seller Profile | UKMart</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://api.fontshare.com/v2/css?f[]=satoshi@300,400,500,600,700&display=swap" rel="stylesheet">
<style>
:root {
  --primary-blue: #2563eb;
  --primary-blue-dark: #1e40af;
  --primary-blue-light: #3b82f6;
  --accent-orange: #f97316;
  --accent-green: #10b981;
  --bg-light: #f8fafc;
  --bg-white: #ffffff;
  --text-dark: #1e293b;
  --text-gray: #64748b;
  --border-color: #e2e8f0;
  --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.12);
  --shadow-md: 0 4px 12px rgba(0, 0, 0, 0.15);
  --shadow-lg: 0 10px 30px rgba(0, 0, 0, 0.2);
  --shadow-xl: 0 20px 40px rgba(0, 0, 0, 0.25);
}

* {
  font-family: 'Satoshi', Georgia, serif !important;
}

body { 
  background: linear-gradient(to bottom, #f8fafc 0%, #ffffff 100%); 
  font-family: 'Satoshi', Georgia, serif; 
  min-height: 100vh;
  padding: 20px 0;
}

.card { 
  border-radius: 24px; 
  box-shadow: var(--shadow-xl);
  border: 2px solid var(--primary-blue-light);
  overflow: hidden;
  position: relative;
}

.card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 5px;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.card h3 {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  font-weight: 800;
  font-size: 2rem;
}

.profile-pic-preview { 
  width: 150px; 
  height: 150px; 
  border-radius: 50%; 
  object-fit: cover; 
  margin-bottom: 20px;
  border: 5px solid var(--primary-blue);
  box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
}

.form-label {
  font-weight: 600;
  color: var(--text-dark);
  margin-bottom: 8px;
}

.form-control {
  border-radius: 12px;
  border: 2px solid var(--border-color);
  padding: 12px 16px;
  transition: all 0.3s ease;
}

.form-control:focus {
  border-color: var(--primary-blue);
  box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
}

.btn-primary {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  border: none;
  border-radius: 12px;
  padding: 14px 30px;
  font-weight: 700;
  font-size: 1.1rem;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
  transition: all 0.3s ease;
}

.btn-primary:hover {
  transform: translateY(-3px);
  box-shadow: 0 12px 30px rgba(102, 126, 234, 0.5);
}

.text-muted {
  font-size: 0.85rem;
  color: var(--text-gray);
}

/* Responsive */
@media(max-width: 768px) {
  .card {
    margin: 10px;
  }
  .profile-pic-preview {
    width: 120px;
    height: 120px;
  }
  .card h3 {
    font-size: 1.5rem;
  }
}
</style>
</head>
<body>
<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card p-4">
                <h3 class="mb-4 text-center">Create Your Seller Profile</h3>
                <form method="post" enctype="multipart/form-data">
                    <!-- Profile Picture Preview -->
                    <div class="mb-3 text-center">
                        <img id="preview" src="profile/default_user.png" alt="Profile Preview" class="profile-pic-preview">
                    </div>

                    <!-- Upload Picture -->
                    <div class="mb-3">
                        <label class="form-label">Profile Picture</label>
                        <input type="file" name="profile_pic" accept=".jpg,.jpeg,.png" class="form-control" onchange="previewImage(event)">
                        <small class="text-muted">Allowed formats: jpg, jpeg, png</small>
                    </div>

                    <!-- Seller Name -->
                    <div class="mb-3">
                        <label class="form-label">Seller Name</label>
                        <input type="text" name="seller_name" class="form-control" required>
                    </div>

                    <!-- Seller Description -->
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="seller_description" class="form-control" rows="4"></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">Create Profile</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function previewImage(event) {
    const reader = new FileReader();
    reader.onload = function(){
        const output = document.getElementById('preview');
        output.src = reader.result;
    }
    reader.readAsDataURL(event.target.files[0]);
}
</script>
</body>
</html>
