<?php 

// //check error
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

include('db_connect.php');
session_start();

// =======================
// Cari seller identifier
// =======================
if (isset($_GET['id'])) {
    $seller_id = $_GET['id'];
} elseif (isset($_SESSION['seller_id'])) {
    $seller_id = $_SESSION['seller_id'];
} elseif (isset($_SESSION['email'])) {
    // fallback: kalau tak ada numeric id, guna email
    $seller_id = $_SESSION['email'];
} else {
    $seller_id = null;
}

$error = "";

// =======================
// Handle form submit
// =======================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name        = isset($_POST['product_name']) ? trim($_POST['product_name']) : '';
    $category    = isset($_POST['product_category']) ? trim($_POST['product_category']) : '';
    $price       = isset($_POST['product_price']) ? floatval($_POST['product_price']) : 0;
    $quantity    = isset($_POST['product_quantity']) ? intval($_POST['product_quantity']) : 0;
    $description = isset($_POST['product_description']) ? trim($_POST['product_description']) : '';  // Optional
    //$deal_method = isset($_POST['deal_method']) ? trim($_POST['deal_method']) : '';  // Deal method (Meetup/Delivery)
    //$deal_method = "Delivery"; // automatically set dummy data

    // extra detail (akan dimasukkan dalam description)
    $cond     = isset($_POST['product_condition']) ? trim($_POST['product_condition']) : '';
    $brand    = isset($_POST['product_brand']) ? trim($_POST['product_brand']) : '';
    $model    = isset($_POST['product_model']) ? trim($_POST['product_model']) : '';
    $location = isset($_POST['product_location']) ? trim($_POST['product_location']) : '';  // Optional
    $phone    = isset($_POST['contact_phone']) ? trim($_POST['contact_phone']) : '';  // Optional

    // Check if all required fields are filled
    if ($name === '' || $category === '' || $price <= 0 || $quantity <= 0 || $cond === '' || $model === '' || $brand === '') {
        $error = "Please fill in all required fields (*).";
    }elseif ($seller_id === null) {
        $error = "Cannot identify seller.";
    } else {
        // Build detailed description
        $detailLines = array();
        if ($cond     !== '') $detailLines[] = "Condition: " . $cond;
        if ($brand    !== '') $detailLines[] = "Brand: " . $brand;
        if ($model    !== '') $detailLines[] = "Model: " . $model;
        if ($location !== '') $detailLines[] = "Location: " . $location;
        if ($phone    !== '') $detailLines[] = "Contact Phone: " . $phone;

        if (!empty($detailLines)) {
            if ($description !== '') {
                $description .= "\n\n";
            }
            $description .= implode("\n", $detailLines);
        }

        // ============ Upload Multiple Images and Videos ============ 
        $image = [];
        $video = [];
        if (isset($_FILES['product_files']) && $_FILES['product_files']['error'][0] == 0) {
            $target_dir_images = "images/";
            $target_dir_videos = "videos/";

            if (!is_dir($target_dir_images)) {
                mkdir($target_dir_images, 0777, true);
            }
            if (!is_dir($target_dir_videos)) {
                mkdir($target_dir_videos, 0777, true);
            }

            $file_paths = [];
            for ($i = 0; $i < count($_FILES['product_files']['name']); $i++) {
                $file_type = $_FILES['product_files']['type'][$i];
                $original_name = basename($_FILES['product_files']['name'][$i]);
                $file_info = pathinfo($original_name);

                if (strpos($file_type, 'image') !== false) {
                    // Handling Image File Upload
                    $target_path = $target_dir_images . $original_name;
                    $counter = 1;
                    while (file_exists($target_path)) {
                        $new_name = $file_info['filename'] . "_" . $counter . "." . $file_info['extension'];
                        $target_path = $target_dir_images . $new_name;
                        $counter++;
                    }
                    $file_paths[] = basename($target_path);
                    move_uploaded_file($_FILES['product_files']['tmp_name'][$i], $target_path);
                } elseif (strpos($file_type, 'video') !== false) {
                    // Handling Video File Upload
                    $target_path = $target_dir_videos . $original_name;
                    $counter = 1;
                    while (file_exists($target_path)) {
                        $new_name = $file_info['filename'] . "_" . $counter . "." . $file_info['extension'];
                        $target_path = $target_dir_videos . $new_name;
                        $counter++;
                    }
                    $file_paths[] = basename($target_path);
                    move_uploaded_file($_FILES['product_files']['tmp_name'][$i], $target_path);
                }
            }
            $image = implode(',', $file_paths); // Store images and videos as a comma-separated list
        }

        // ============ Insert DB ============ 
        if ($error === '') {
            $stmt = $conn->prepare("INSERT INTO tbl_products_ukmart 
                (fld_product_name, fld_product_category, fld_product_price, 
                 fld_product_quantity, fld_product_description, fld_product_image, fld_product_model, fld_product_condition, 
                 fld_product_location, fld_contact_phone, fld_seller_id, fld_product_brand) 
                VALUES (:name, :cat, :price, :qty, :desc, :img, :model, :cond, :loc, :phone, :sid, :brand)");

            $stmt->bindParam(':name',  $name);
            $stmt->bindParam(':cat',   $category);
            $stmt->bindParam(':price', $price);
            $stmt->bindParam(':qty',   $quantity);
            $stmt->bindParam(':desc',  $description);
            $stmt->bindParam(':img',   $image); // Only store images as a list
            $stmt->bindParam(':model', $model); // Model/Variant
            $stmt->bindParam(':cond',  $cond);  // Condition
            $stmt->bindParam(':loc',   $location); // Location (Optional)
            $stmt->bindParam(':phone', $phone); // Phone number (Optional)
            $stmt->bindParam(':sid',   $seller_id);
            //$stmt->bindParam(':deal',  $deal_method);
            $stmt->bindParam(':brand', $brand);

            // //check error
            // if (!$stmt->execute()) {
            //     echo "DB Error: ";
            //     print_r($stmt->errorInfo());
            //     exit;
            // }

            // echo "<pre>";
            // print_r($_FILES);
            // echo "</pre>";

            if ($stmt->execute()) {
                // After adding product, go to seller page
                header("Location: seller.php"); // Redirect to seller page after product submission
                exit(); // Make sure to call exit after redirect
            } else {
                $error = "Failed to add product. Please try again.";
            }

            
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Add Product | UKMart</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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
    body {
        background: linear-gradient(to bottom, #f8fafc 0%, #ffffff 100%);
        font-family: 'Poppins', sans-serif;
        color: var(--text-dark);
    }
    .top-bar {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: #fff;
        padding: 15px 25px;
        font-weight: 700;
        box-shadow: 0 4px 20px rgba(102, 126, 234, 0.4);
        text-transform: uppercase;
        letter-spacing: 1px;
        font-size: 18px;
    }
    .step-section { display:none; }
    .step-section.active { display:block; }
    .nav-pills .nav-link.disabled { pointer-events:none; opacity:.5; }
    .section-block {
        border: 2px solid var(--primary-blue-light);
        border-radius: 16px;
        background: linear-gradient(to bottom, #ffffff 0%, #f8fafc 100%);
        padding: 25px;
        margin-bottom: 25px;
        box-shadow: var(--shadow-md);
        position: relative;
        overflow: hidden;
    }
    .section-block::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }
    .section-title {
        font-weight: 600;
        margin-bottom: 10px;
    }
    .section-sub {
        font-size: 0.85rem;
        color: #777;
        margin-bottom: 12px;
    }

    /* Custom File Upload UI (Drag and Drop Style) */
    .file-upload {
        border: 3px dashed var(--primary-blue);
        border-radius: 16px;
        padding: 40px 20px;
        text-align: center;
        background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
        cursor: pointer;
        color: var(--primary-blue);
        transition: all 0.4s ease;
        position: relative;
        overflow: hidden;
    }
    .file-upload::before {
        content: '📁';
        font-size: 3rem;
        display: block;
        margin-bottom: 15px;
        opacity: 0.5;
    }
    .file-upload:hover {
        background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
        border-color: var(--primary-blue-dark);
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
    }
    .file-upload input {
        display: none;
    }
    .file-upload span {
        font-size: 16px;
    }
    .file-upload .file-names {
        margin-top: 10px;
        font-size: 14px;
        color: #666;
    }

    /* Preview images and videos */
    .file-preview {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-top: 10px;
    }
    .file-preview img {
        width: 100px;
        height: 100px;
        object-fit: cover;
        border-radius: 10px;
    }
    .file-preview .file-item {
        display: inline-block;
    }
</style>
</head>

<body>
<!-- SELLER NAVBAR -->
<?php include('seller_nav_bar.php'); ?>

<div class="top-bar">
    UKMart – Add Product
</div>

<div class="container mt-4">
    <a href="seller.php" class="btn btn-outline-secondary btn-sm mb-3">←Back </a>

    <div class="card shadow-sm p-4 mx-auto" style="max-width: 900px;">
        <h4 class="fw-bold mb-3">Add New Product</h4>

        <?php if (!empty($error)) : ?>
            <div class="alert alert-danger py-2"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <ul class="nav nav-pills mb-3">
            <li class="nav-item">
                <a class="nav-link active" id="tab1">1. Basic Info</a>
            </li>
            <li class="nav-item">
                <a class="nav-link disabled" id="tab2">2. Product Details</a>
            </li>
            <li class="nav-item">
                <a class="nav-link disabled" id="tab3">3. Additional Info</a>
            </li>
        </ul>

        <form method="POST" enctype="multipart/form-data" id="productForm">

            <!-- STEP 1: Basic Info -->
            <div id="step1" class="step-section active">
                <div class="section-block">
                    <div class="section-title">Basic Info</div>
                    <div class="mb-3">
                        <label class="form-label">Product Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="product_name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Category <span class="text-danger">*</span></label>
                        <select name="product_category" id="categorySelect" class="form-select" required>
                            <option value="">-- Select Category --</option>
                            <option value="Food">Food</option>
                            <option value="Book">Book</option>
                            <option value="Clothing">Clothing</option>
                            <option value="Dorm supply">Dorm supply</option>
                            <option value="Electronics">Electronics</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Price <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0" class="form-control" name="product_price" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Quantity <span class="text-danger">*</span></label>
                        <input type="number" min="1" class="form-control" name="product_quantity" required>
                    </div>
                </div>

                <button type="button" class="btn btn-primary float-end" id="goStep2">Next→</button>
            </div>

            <!-- STEP 2: Product Details -->
<div id="step2" class="step-section">
    <!-- Product Specifications -->
    <div class="section-block">
        <div class="section-title">Product Specifications</div>
        <div class="mb-3">
            <label class="form-label">Model / Variant <span class="text-danger">*</span></label>
            <input type="text" class="form-control" name="product_model" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Brand <span class="text-danger">*</span></label>
            <input type="text" class="form-control" name="product_brand" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Condition <span class="text-danger">*</span></label>
            <select class="form-select" name="product_condition" required>
                <option value="">Select condition</option>
                <option value="New">New</option>
                <option value="Lightly Used">Lightly Used</option>
                <option value="Heavily Used">Heavily Used</option>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label">Description</label>
            <textarea class="form-control" name="product_description" rows="4" placeholder="Describe your product"></textarea>
        </div>
    </div>

    <button type="button" class="btn btn-outline-secondary" id="backStep1">← Back</button>
    <button type="button" class="btn btn-primary float-end" id="goStep3">Next→</button>
</div>


            <!-- STEP 3: Additional Info -->
            <div id="step3" class="step-section">
                <!-- Photos -->
                <!-- Photos -->
<!-- Photos -->
<div class="section-block">
    <div class="section-title">Upload Photos</div>
    <div class="section-sub">Upload clear photos to attract buyers.</div>
    <div class="file-upload" onclick="document.getElementById('product_files').click()">
        <span>Click here to select photos</span>
        <div class="file-names" id="fileNames"></div>
    </div>
    <input type="file" name="product_files[]" accept="image/*" multiple id="product_files" required style="display:none;">
    <div class="file-preview" id="filePreview"></div>
</div>

                <!-- Contact Information -->
                <div class="section-block">
                    <div class="section-title">Contact Information</div>
                    <div class="mb-3">
                        <label class="form-label">Phone (10-11 digits)</label>
                        <input type="text" class="form-control" name="contact_phone" pattern="^[0-9]{10,11}$" placeholder="Enter your phone number" >
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Location</label>
                        <input type="text" class="form-control" name="product_location">
                    </div>
                </div>

                <button type="button" class="btn btn-outline-secondary" id="backStep2">← Back</button>
                <button type="submit" class="btn btn-success float-end">Submit Product</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Step 1 to Step 2
document.getElementById('goStep2').onclick = function () {
    var cat = document.getElementById('categorySelect').value;
    if (!cat) {
        alert('Please select a category first.');
        return;
    }
    document.getElementById('step1').classList.remove('active');
    document.getElementById('step2').classList.add('active');

    document.getElementById('tab1').classList.remove('active');
    document.getElementById('tab1').classList.add('disabled');
    document.getElementById('tab2').classList.remove('disabled');
    document.getElementById('tab2').classList.add('active');
};

// Step 2 to Step 3
document.getElementById('goStep3').onclick = function () {
    document.getElementById('step2').classList.remove('active');
    document.getElementById('step3').classList.add('active');

    document.getElementById('tab2').classList.remove('active');
    document.getElementById('tab2').classList.add('disabled');
    document.getElementById('tab3').classList.remove('disabled');
    document.getElementById('tab3').classList.add('active');
};

// Back Step 1
document.getElementById('backStep1').onclick = function () {
    document.getElementById('step2').classList.remove('active');
    document.getElementById('step1').classList.add('active');

    document.getElementById('tab2').classList.remove('active');
    document.getElementById('tab2').classList.add('disabled');
    document.getElementById('tab1').classList.add('active');
};

// Back Step 2
document.getElementById('backStep2').onclick = function () {
    document.getElementById('step3').classList.remove('active');
    document.getElementById('step2').classList.add('active');

    document.getElementById('tab3').classList.remove('active');
    document.getElementById('tab3').classList.add('disabled');
    document.getElementById('tab2').classList.add('active');
};

// Preview file names and handle drag & drop
const fileInput = document.getElementById('product_files');
const fileUpload = document.querySelector('.file-upload');
const filePreview = document.getElementById('filePreview');
const fileNames = document.getElementById('fileNames');

// Handle file input change
fileInput.addEventListener('change', function () {
    handleFiles(this.files);
});

// Drag and drop handlers
fileUpload.addEventListener('dragover', function(e) {
    e.preventDefault();
    e.stopPropagation();
    this.style.background = 'linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%)';
    this.style.borderColor = 'var(--primary-blue-dark)';
});

fileUpload.addEventListener('dragleave', function(e) {
    e.preventDefault();
    e.stopPropagation();
    this.style.background = 'linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%)';
    this.style.borderColor = 'var(--primary-blue)';
});

fileUpload.addEventListener('drop', function(e) {
    e.preventDefault();
    e.stopPropagation();
    this.style.background = 'linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%)';
    this.style.borderColor = 'var(--primary-blue)';
    
    const files = e.dataTransfer.files;
    if (files.length > 0) {
        // Create a new FileList-like object
        const dataTransfer = new DataTransfer();
        Array.from(files).forEach(file => {
            if (file.type.startsWith('image/')) {
                dataTransfer.items.add(file);
            }
        });
        fileInput.files = dataTransfer.files;
        handleFiles(fileInput.files);
    }
});

// Function to handle files
function handleFiles(files) {
    filePreview.innerHTML = ''; // Clear previous previews
    fileNames.innerHTML = ''; // Clear previous file names
    
    if (files.length === 0) return;
    
    const fileList = [];
    [...files].forEach((file, index) => {
        fileList.push(file.name);
        
        if (file.type.startsWith('image/')) {
            const fileItem = document.createElement('div');
            fileItem.style.cssText = 'position: relative; display: inline-block; margin: 5px;';
            
            const img = document.createElement('img');
            img.src = URL.createObjectURL(file);
            img.alt = file.name;
            img.style.cssText = 'width: 100px; height: 100px; object-fit: cover; border-radius: 8px; border: 2px solid var(--primary-blue-light);';
            
            const removeBtn = document.createElement('button');
            removeBtn.innerHTML = '×';
            removeBtn.type = 'button';
            removeBtn.style.cssText = 'position: absolute; top: -5px; right: -5px; background: red; color: white; border: none; border-radius: 50%; width: 24px; height: 24px; cursor: pointer; font-size: 18px; line-height: 1;';
            removeBtn.onclick = function() {
                // Remove file from input
                const dt = new DataTransfer();
                Array.from(fileInput.files).forEach((f, i) => {
                    if (i !== index) dt.items.add(f);
                });
                fileInput.files = dt.files;
                handleFiles(fileInput.files);
            };
            
            fileItem.appendChild(img);
            fileItem.appendChild(removeBtn);
            filePreview.appendChild(fileItem);
        }
    });
    
    if (fileList.length > 0) {
        fileNames.innerHTML = '<strong>Selected:</strong> ' + fileList.join(', ');
    }
}
</script>

</body>
</html>
