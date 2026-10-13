<?php

require '../../config/config.php';

requireRole('manager');

requireCsrfToken();

// Determine current section
$section = $_GET['section'] ?? 'contractors';

// CRUD Operations
$action = $_GET['action'] ?? '';

//-----------------------------------------------------------
// Contractors
//-----------------------------------------------------------

// Fetch contractors (with linked user info)
if ($section === 'contractors') {

    $stmt = $pdo->query("
        SELECT
            c.*,
            u.user_username,
            u.user_email,
            u.user_role,
            u.user_verified
        FROM contractors c
        LEFT JOIN users u ON c.user_id = u.user_id
        ORDER BY c.contractor_id DESC
    ");

    $contractors = $stmt->fetchAll();
}

// Create Contractor + Linked User (Solution A)
if ($section === 'contractors' && $action === 'create') {

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        // Contractor fields
        $firstName = trim($_POST['contractor_first_name'] ?? '');
        $lastName  = trim($_POST['contractor_last_name'] ?? '');
        $specialty = trim($_POST['contractor_specialty'] ?? '');

        // User fields
        $email    = trim($_POST['user_email'] ?? '');
        $username = trim($_POST['user_username'] ?? '');
        $password = $_POST['user_password'] ?? '';

        // Validate all required fields
        if (
            $firstName === '' || $lastName === '' || $specialty === '' ||
            $email === '' || $username === '' || $password === ''
        ) {
            $_SESSION['alert'] = 'Please fill in all contractor and user account fields.';
            header("Location: index.php?section=contractors&action=create");
            exit;
        }

        // Validate password length
        if (strlen($password) < 8) {
            $_SESSION['alert'] = 'Password must be at least 8 characters.';
            header("Location: index.php?section=contractors&action=create");
            exit;
        }

        // Check for duplicate email/username
        $stmt = $pdo->prepare("
            SELECT user_id
            FROM users
            WHERE user_email = ? OR user_username = ?
            LIMIT 1
        ");
        $stmt->execute([$email, $username]);

        if ($stmt->fetch()) {
            $_SESSION['alert'] = 'Email or username already exists.';
            header("Location: index.php?section=contractors&action=create");
            exit;
        }

        try {

            $pdo->beginTransaction();

            // 1. Create the user account (role = 'user', unverified)
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("
                INSERT INTO users (
                    user_email,
                    user_username,
                    user_password,
                    user_role,
                    user_verified
                )
                VALUES (?, ?, ?, 'user', 0)
            ");

            $stmt->execute([
                $email,
                $username,
                $hashedPassword
            ]);

            $newUserId = (int) $pdo->lastInsertId();

            // 2. Create the contractor linked to that user
            $stmt = $pdo->prepare("
                INSERT INTO contractors (
                    user_id,
                    contractor_first_name,
                    contractor_last_name,
                    contractor_specialty
                )
                VALUES (?, ?, ?, ?)
            ");

            $stmt->execute([
                $newUserId,
                $firstName,
                $lastName,
                $specialty
            ]);

            $pdo->commit();

            if (isset($_SESSION['user_id'])) {
                logActivity(
                    $pdo,
                    $_SESSION['user_id'],
                    $_SESSION['user_email'] ?? null,
                    'create-contractor',
                    'success'
                );
            }

            $_SESSION['alert'] =
                'Contractor and user account created. User is pending verification.';

            header("Location: index.php?section=contractors");
            exit;

        } catch (Exception $e) {

            $pdo->rollBack();

            if (isset($_SESSION['user_id'])) {
                logActivity(
                    $pdo,
                    $_SESSION['user_id'],
                    $_SESSION['user_email'] ?? null,
                    'create-contractor',
                    'failed'
                );
            }

            $_SESSION['alert'] = 'Error creating contractor: ' . $e->getMessage();

            header("Location: index.php?section=contractors&action=create");
            exit;
        }
    }
}

// Update Contractor (does NOT change linked user here)
if ($section === 'contractors' && $action === 'update') {

    $contractorId = (int) ($_GET['id'] ?? 0);

    $stmt = $pdo->prepare("
        SELECT
            c.*,
            u.user_username,
            u.user_email,
            u.user_role,
            u.user_verified
        FROM contractors c
        LEFT JOIN users u ON c.user_id = u.user_id
        WHERE c.contractor_id = ?
    ");

    $stmt->execute([$contractorId]);

    $contractor = $stmt->fetch();

    if (!$contractor) {
        die("Contractor not found.");
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        $firstName = trim($_POST['contractor_first_name'] ?? '');
        $lastName  = trim($_POST['contractor_last_name'] ?? '');
        $specialty = trim($_POST['contractor_specialty'] ?? '');

        if ($firstName !== '' && $lastName !== '' && $specialty !== '') {

            $stmt = $pdo->prepare("
                UPDATE contractors
                SET
                    contractor_first_name = ?,
                    contractor_last_name = ?,
                    contractor_specialty = ?
                WHERE contractor_id = ?
            ");

            $stmt->execute([
                $firstName,
                $lastName,
                $specialty,
                $contractorId
            ]);

            if (isset($_SESSION['user_id'])) {
                logActivity(
                    $pdo,
                    $_SESSION['user_id'],
                    $_SESSION['user_email'] ?? null,
                    'update-contractor',
                    'success'
                );
            }

            $_SESSION['alert'] = 'Contractor updated successfully.';

            header("Location: index.php?section=contractors");
            exit;
        }

        $_SESSION['alert'] = 'Please fill in all required contractor fields.';
    }
}

// Verify Contractor's User Account
if ($section === 'contractors' && $action === 'verify') {

    $contractorId = (int) ($_GET['id'] ?? 0);

    $stmt = $pdo->prepare("
        SELECT user_id
        FROM contractors
        WHERE contractor_id = ?
    ");
    $stmt->execute([$contractorId]);
    $row = $stmt->fetch();

    if (!$row || empty($row['user_id'])) {
        $_SESSION['alert'] = 'This contractor has no linked user account.';
        header("Location: index.php?section=contractors");
        exit;
    }

    $stmt = $pdo->prepare("
        UPDATE users
        SET user_verified = 1
        WHERE user_id = ?
    ");
    $stmt->execute([$row['user_id']]);

    if (isset($_SESSION['user_id'])) {
        logActivity(
            $pdo,
            $_SESSION['user_id'],
            $_SESSION['user_email'] ?? null,
            'verify-contractor',
            'success'
        );
    }

    $_SESSION['alert'] = 'Contractor user account verified.';

    header("Location: index.php?section=contractors");
    exit;
}

// Unverify Contractor's User Account
if ($section === 'contractors' && $action === 'unverify') {

    $contractorId = (int) ($_GET['id'] ?? 0);

    $stmt = $pdo->prepare("
        SELECT user_id
        FROM contractors
        WHERE contractor_id = ?
    ");
    $stmt->execute([$contractorId]);
    $row = $stmt->fetch();

    if (!$row || empty($row['user_id'])) {
        $_SESSION['alert'] = 'This contractor has no linked user account.';
        header("Location: index.php?section=contractors");
        exit;
    }

    $stmt = $pdo->prepare("
        UPDATE users
        SET user_verified = 0
        WHERE user_id = ?
    ");
    $stmt->execute([$row['user_id']]);

    if (isset($_SESSION['user_id'])) {
        logActivity(
            $pdo,
            $_SESSION['user_id'],
            $_SESSION['user_email'] ?? null,
            'unverify-contractor',
            'success'
        );
    }

    $_SESSION['alert'] = 'Contractor user account unverified.';

    header("Location: index.php?section=contractors");
    exit;
}

// Delete Contractor (and linked user)
if ($section === 'contractors' && $action === 'delete') {

    $contractorId = (int) ($_GET['id'] ?? 0);

    // Block if contractor has project updates
    $stmt = $pdo->prepare("
        SELECT update_id
        FROM project_updates
        WHERE contractor_id = ?
        LIMIT 1
    ");
    $stmt->execute([$contractorId]);

    if ($stmt->fetch()) {
        $_SESSION['alert'] = 'Cannot delete: this contractor has existing project updates.';
        header("Location: index.php?section=contractors");
        exit;
    }

    try {

        $pdo->beginTransaction();

        // Find the linked user
        $stmt = $pdo->prepare("
            SELECT user_id
            FROM contractors
            WHERE contractor_id = ?
        ");
        $stmt->execute([$contractorId]);
        $row = $stmt->fetch();

        // Delete contractor
        $stmt = $pdo->prepare("
            DELETE FROM contractors
            WHERE contractor_id = ?
        ");
        $stmt->execute([$contractorId]);

        // Delete linked user
        if ($row && !empty($row['user_id'])) {
            $stmt = $pdo->prepare("
                DELETE FROM users
                WHERE user_id = ?
            ");
            $stmt->execute([$row['user_id']]);
        }

        $pdo->commit();

        if (isset($_SESSION['user_id'])) {
            logActivity(
                $pdo,
                $_SESSION['user_id'],
                $_SESSION['user_email'] ?? null,
                'delete-contractor',
                'success'
            );
        }

        $_SESSION['alert'] = 'Contractor and linked user account deleted.';

    } catch (Exception $e) {

        $pdo->rollBack();

        $_SESSION['alert'] = 'Error deleting contractor: ' . $e->getMessage();
    }

    header("Location: index.php?section=contractors");
    exit;
}


//-----------------------------------------------------------
// Projects
//-----------------------------------------------------------

if ($section === 'projects') {

    $stmt = $pdo->query("
        SELECT *
        FROM projects
        ORDER BY project_id DESC
    ");

    $projects = $stmt->fetchAll();
}

// Create Project
if ($section === 'projects' && $action === 'create') {

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        $title    = trim($_POST['project_title'] ?? '');
        $manager  = trim($_POST['project_manager'] ?? '');
        $category = trim($_POST['project_category'] ?? '');

        if ($title !== '' && $manager !== '' && $category !== '') {

            $stmt = $pdo->prepare("
                INSERT INTO projects (
                    project_title,
                    project_manager,
                    project_category
                )
                VALUES (?, ?, ?)
            ");

            $stmt->execute([$title, $manager, $category]);

            if (isset($_SESSION['user_id'])) {
                logActivity(
                    $pdo,
                    $_SESSION['user_id'],
                    $_SESSION['user_email'] ?? null,
                    'add-project',
                    'success'
                );
            }

            $_SESSION['alert'] = 'Project created successfully.';

            header("Location: index.php?section=projects");
            exit;
        }

        $_SESSION['alert'] = 'Please fill in all required project fields.';
    }
}

// Update Project
if ($section === 'projects' && $action === 'update') {

    $projectId = (int) ($_GET['id'] ?? 0);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        $title    = trim($_POST['project_title'] ?? '');
        $manager  = trim($_POST['project_manager'] ?? '');
        $category = trim($_POST['project_category'] ?? '');

        if ($title !== '' && $manager !== '' && $category !== '') {

            $stmt = $pdo->prepare("
                UPDATE projects
                SET
                    project_title = ?,
                    project_manager = ?,
                    project_category = ?
                WHERE project_id = ?
            ");

            $stmt->execute([$title, $manager, $category, $projectId]);

            if (isset($_SESSION['user_id'])) {
                logActivity(
                    $pdo,
                    $_SESSION['user_id'],
                    $_SESSION['user_email'] ?? null,
                    'update-project',
                    'success'
                );
            }

            $_SESSION['alert'] = 'Project updated successfully.';

            header("Location: index.php?section=projects");
            exit;
        }

        $_SESSION['alert'] = 'Please fill in all required project fields.';
    }

    $stmt = $pdo->prepare("
        SELECT *
        FROM projects
        WHERE project_id = ?
    ");
    $stmt->execute([$projectId]);
    $project = $stmt->fetch();

    if (!$project) {
        die("Project not found.");
    }
}

// Delete Project
if ($section === 'projects' && $action === 'delete') {

    $projectId = (int) ($_GET['id'] ?? 0);

    $stmt = $pdo->prepare("
        SELECT update_id
        FROM project_updates
        WHERE project_id = ?
        LIMIT 1
    ");
    $stmt->execute([$projectId]);

    if ($stmt->fetch()) {
        $_SESSION['alert'] = 'Cannot delete: this project has existing updates.';
        header("Location: index.php?section=projects");
        exit;
    }

    $stmt = $pdo->prepare("
        DELETE FROM projects
        WHERE project_id = ?
    ");
    $stmt->execute([$projectId]);

    if (isset($_SESSION['user_id'])) {
        logActivity(
            $pdo,
            $_SESSION['user_id'],
            $_SESSION['user_email'] ?? null,
            'delete-project',
            'success'
        );
    }

    $_SESSION['alert'] = 'Project deleted successfully.';

    header("Location: index.php?section=projects");
    exit;
}


//-----------------------------------------------------------
// Project Updates
//-----------------------------------------------------------

if ($section === 'updates') {

    $stmt = $pdo->query("
        SELECT
            contractor_id,
            contractor_first_name,
            contractor_last_name
        FROM contractors
        ORDER BY contractor_last_name, contractor_first_name
    ");
    $contractors = $stmt->fetchAll();

    $stmt = $pdo->query("
        SELECT
            project_id,
            project_title,
            project_manager
        FROM projects
        ORDER BY project_title
    ");
    $projects = $stmt->fetchAll();

    $stmt = $pdo->query("
        SELECT
            project_updates.update_id,
            project_updates.update_date,
            project_updates.update_due_date,
            project_updates.update_completion_date,

            contractors.contractor_first_name,
            contractors.contractor_last_name,

            projects.project_title,
            projects.project_manager

        FROM project_updates

        INNER JOIN contractors
            ON project_updates.contractor_id = contractors.contractor_id

        INNER JOIN projects
            ON project_updates.project_id = projects.project_id

        ORDER BY project_updates.update_id DESC
    ");
    $updateRecords = $stmt->fetchAll();
}

// Add Project Update
if ($section === 'updates' && $action === 'create') {

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        $contractorId = (int) ($_POST['contractor_id'] ?? 0);
        $projectId    = (int) ($_POST['project_id'] ?? 0);
        $dueDate      = $_POST['update_due_date'] ?? '';

        if ($contractorId > 0 && $projectId > 0 && $dueDate !== '') {

            $stmt = $pdo->prepare("
                SELECT update_id
                FROM project_updates
                WHERE contractor_id = ?
                  AND update_completion_date IS NULL
                LIMIT 1
            ");
            $stmt->execute([$contractorId]);

            if ($stmt->fetch()) {

                $_SESSION['alert'] =
                    'This contractor cannot submit another update because a previous update has not been completed.';

            } else {

                $stmt = $pdo->prepare("
                    SELECT update_id
                    FROM project_updates
                    WHERE project_id = ?
                      AND update_completion_date IS NULL
                    LIMIT 1
                ");
                $stmt->execute([$projectId]);

                if ($stmt->fetch()) {

                    $_SESSION['alert'] =
                        'This project cannot be updated because a previous update has not been completed.';

                } else {

                    $stmt = $pdo->prepare("
                        INSERT INTO project_updates (
                            contractor_id,
                            project_id,
                            update_due_date
                        )
                        VALUES (?, ?, ?)
                    ");
                    $stmt->execute([$contractorId, $projectId, $dueDate]);

                    if (isset($_SESSION['user_id'])) {
                        logActivity(
                            $pdo,
                            $_SESSION['user_id'],
                            $_SESSION['user_email'] ?? null,
                            'add-project-update',
                            'success'
                        );
                    }

                    $_SESSION['alert'] = 'Project update added successfully.';
                }
            }

            header("Location: index.php?section=updates");
            exit;
        }

        $_SESSION['alert'] = 'Please select a contractor, project, and due date.';
    }
}

// Complete Project Update
if ($section === 'updates' && $action === 'complete') {

    $updateId = (int) ($_GET['id'] ?? 0);

    $stmt = $pdo->prepare("
        UPDATE project_updates
        SET update_completion_date = CURRENT_TIMESTAMP
        WHERE update_id = ?
    ");
    $stmt->execute([$updateId]);

    if (isset($_SESSION['user_id'])) {
        logActivity(
            $pdo,
            $_SESSION['user_id'],
            $_SESSION['user_email'] ?? null,
            'complete-project-update',
            'success'
        );
    }

    $_SESSION['alert'] = 'Project update completed successfully.';

    header("Location: index.php?section=updates");
    exit;
}

// Delete Project Update
if ($section === 'updates' && $action === 'delete') {

    $updateId = (int) ($_GET['id'] ?? 0);

    $stmt = $pdo->prepare("
        DELETE FROM project_updates
        WHERE update_id = ?
    ");
    $stmt->execute([$updateId]);

    if (isset($_SESSION['user_id'])) {
        logActivity(
            $pdo,
            $_SESSION['user_id'],
            $_SESSION['user_email'] ?? null,
            'delete-project-update',
            'success'
        );
    }

    $_SESSION['alert'] = 'Project update deleted successfully.';

    header("Location: index.php?section=updates");
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project Management</title>
</head>
<body>
    <form method="POST" action="../../auth/signout.php">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>">
        <button type="submit">Sign Out</button>
    </form>

    <h1>Project Management System</h1>

    <nav>
        <a href="index.php?section=contractors">Contractors</a>
        |
        <a href="index.php?section=projects">Projects</a>
        |
        <a href="index.php?section=updates">Project Updates</a>
    </nav>

    <hr>

    <!-- =========================================================
         CONTRACTORS SECTION
         ========================================================= -->
    <?php if ($section === 'contractors'): ?>

        <h1>Contractors</h1>

        <p>
            <a href="index.php?section=contractors&action=create">
                Add Contractor
            </a>
        </p>

        <?php if ($action === 'create'): ?>

            <h2>Add Contractor</h2>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>">

                <fieldset>
                    <legend>Contractor Details</legend>

                    <p>
                        <label>First Name</label><br>
                        <input type="text" name="contractor_first_name" required>
                    </p>

                    <p>
                        <label>Last Name</label><br>
                        <input type="text" name="contractor_last_name" required>
                    </p>

                    <p>
                        <label>Specialty</label><br>
                        <input type="text" name="contractor_specialty" required>
                    </p>
                </fieldset>

                <fieldset>
                    <legend>Linked User Account</legend>

                    <p>
                        <label>Email</label><br>
                        <input type="email" name="user_email" required>
                    </p>

                    <p>
                        <label>Username</label><br>
                        <input type="text" name="user_username" required>
                    </p>

                    <p>
                        <label>Password</label><br>
                        <input
                            type="password"
                            name="user_password"
                            required
                            minlength="8"
                            placeholder="At least 8 characters"
                        >
                    </p>

                    <p>
                        <em>
                            Note: The user account will be created with role
                            <strong>user</strong> and marked
                            <strong>unverified</strong>. You can verify it
                            from the contractor list after creation.
                        </em>
                    </p>
                </fieldset>

                <button type="submit">Save</button>

                <a href="index.php?section=contractors">Cancel</a>
            </form>

        <?php elseif ($action === 'update'): ?>

            <h2>Update Contractor</h2>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>">

                <p>
                    <label>First Name</label><br>
                    <input
                        type="text"
                        name="contractor_first_name"
                        value="<?= htmlspecialchars($contractor['contractor_first_name']) ?>"
                        required
                    >
                </p>

                <p>
                    <label>Last Name</label><br>
                    <input
                        type="text"
                        name="contractor_last_name"
                        value="<?= htmlspecialchars($contractor['contractor_last_name']) ?>"
                        required
                    >
                </p>

                <p>
                    <label>Specialty</label><br>
                    <input
                        type="text"
                        name="contractor_specialty"
                        value="<?= htmlspecialchars($contractor['contractor_specialty']) ?>"
                        required
                    >
                </p>

                <?php if (!empty($contractor['user_id'])): ?>

                    <fieldset>
                        <legend>Linked User Account</legend>

                        <p>
                            <strong>Username:</strong>
                            <?= htmlspecialchars($contractor['user_username'] ?? '-') ?>
                            <br>
                            <strong>Email:</strong>
                            <?= htmlspecialchars($contractor['user_email'] ?? '-') ?>
                            <br>
                            <strong>Role:</strong>
                            <?= htmlspecialchars($contractor['user_role'] ?? '-') ?>
                            <br>
                            <strong>Verified:</strong>
                            <?= $contractor['user_verified'] ? 'Yes' : 'No' ?>
                        </p>

                        <p>
                            <em>
                                To edit the linked user account, use
                                <a href="../admin/users.php?section=users&action=update&id=<?= (int) $contractor['user_id'] ?>">
                                    Admin → Users
                                </a>.
                            </em>
                        </p>
                    </fieldset>

                <?php endif; ?>

                <button type="submit">Update</button>

                <a href="index.php?section=contractors">Cancel</a>
            </form>

        <?php else: ?>

            <table border="1" cellpadding="8">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>First Name</th>
                        <th>Last Name</th>
                        <th>Specialty</th>
                        <th>Linked User</th>
                        <th>Verified</th>
                        <th>Created At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($contractors as $contractor): ?>
                        <tr>
                            <td>
                                <?= htmlspecialchars($contractor['contractor_id']) ?>
                            </td>
                            <td>
                                <?= htmlspecialchars($contractor['contractor_first_name']) ?>
                            </td>
                            <td>
                                <?= htmlspecialchars($contractor['contractor_last_name']) ?>
                            </td>
                            <td>
                                <?= htmlspecialchars($contractor['contractor_specialty']) ?>
                            </td>
                            <td>
                                <?php if (!empty($contractor['user_id'])): ?>

                                    <strong>
                                        <?= htmlspecialchars(
                                            $contractor['user_username']
                                            ?? ('User #' . $contractor['user_id'])
                                        ) ?>
                                    </strong>
                                    <br>
                                    <small>
                                        <?= htmlspecialchars($contractor['user_email'] ?? '') ?>
                                    </small>

                                <?php else: ?>

                                    <em>—</em>

                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($contractor['user_id'])): ?>

                                    <?php if ($contractor['user_verified']): ?>
                                        <strong style="color: green;">Verified</strong>
                                    <?php else: ?>
                                        <strong style="color: red;">Unverified</strong>
                                    <?php endif; ?>

                                <?php else: ?>

                                    <em>—</em>

                                <?php endif; ?>
                            </td>
                            <td>
                                <?= htmlspecialchars($contractor['contractor_created_at']) ?>
                            </td>
                            <td>
                                <a href="index.php?section=contractors&action=update&id=<?= (int) $contractor['contractor_id'] ?>">
                                    Edit
                                </a>

                                |

                                <?php if (!empty($contractor['user_id'])): ?>

                                    <?php if ($contractor['user_verified']): ?>
                                        <a
                                            href="index.php?section=contractors&action=unverify&id=<?= (int) $contractor['contractor_id'] ?>"
                                            onclick="return confirm('Unverify this contractor\'s user account?');"
                                        >
                                            Unverify
                                        </a>
                                    <?php else: ?>
                                        <a
                                            href="index.php?section=contractors&action=verify&id=<?= (int) $contractor['contractor_id'] ?>"
                                            onclick="return confirm('Verify this contractor\'s user account?');"
                                        >
                                            Verify
                                        </a>
                                    <?php endif; ?>

                                    |

                                <?php endif; ?>

                                <a
                                    href="index.php?section=contractors&action=delete&id=<?= (int) $contractor['contractor_id'] ?>"
                                    onclick="return confirm('Delete this contractor and its linked user account?');"
                                >
                                    Delete
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

        <?php endif; ?>

    <?php endif; ?>


    <!-- =========================================================
         PROJECTS SECTION
         ========================================================= -->
    <?php if ($section === 'projects'): ?>

        <h2>Projects</h2>

        <p>
            <a href="index.php?section=projects&action=create">
                Add Project
            </a>
        </p>

        <?php if ($action === 'create'): ?>

            <h3>Add Project</h3>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>">

                <p>
                    <label>Title:</label><br>
                    <input type="text" name="project_title" required>
                </p>

                <p>
                    <label>Manager:</label><br>
                    <input type="text" name="project_manager" required>
                </p>

                <p>
                    <label>Category:</label><br>
                    <input type="text" name="project_category" required>
                </p>

                <button type="submit">Save</button>
                <a href="index.php?section=projects">Cancel</a>
            </form>

        <?php elseif ($action === 'update'): ?>

            <h3>Update Project</h3>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>">

                <p>
                    <label>Title:</label><br>
                    <input
                        type="text"
                        name="project_title"
                        value="<?= htmlspecialchars($project['project_title']) ?>"
                        required
                    >
                </p>

                <p>
                    <label>Manager:</label><br>
                    <input
                        type="text"
                        name="project_manager"
                        value="<?= htmlspecialchars($project['project_manager']) ?>"
                        required
                    >
                </p>

                <p>
                    <label>Category:</label><br>
                    <input
                        type="text"
                        name="project_category"
                        value="<?= htmlspecialchars($project['project_category']) ?>"
                        required
                    >
                </p>

                <button type="submit">Update</button>
                <a href="index.php?section=projects">Cancel</a>
            </form>

        <?php else: ?>

            <table border="1" cellpadding="8">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Title</th>
                        <th>Manager</th>
                        <th>Category</th>
                        <th>Created At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($projects as $project): ?>
                        <tr>
                            <td><?= htmlspecialchars($project['project_id']) ?></td>
                            <td><?= htmlspecialchars($project['project_title']) ?></td>
                            <td><?= htmlspecialchars($project['project_manager']) ?></td>
                            <td><?= htmlspecialchars($project['project_category']) ?></td>
                            <td><?= htmlspecialchars($project['project_created_at']) ?></td>
                            <td>
                                <a href="index.php?section=projects&action=update&id=<?= (int) $project['project_id'] ?>">
                                    Edit
                                </a>
                                |
                                <a
                                    href="index.php?section=projects&action=delete&id=<?= (int) $project['project_id'] ?>"
                                    onclick="return confirm('Delete this project?');"
                                >
                                    Delete
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

        <?php endif; ?>

    <?php endif; ?>


    <!-- =========================================================
         PROJECT UPDATES SECTION
         ========================================================= -->
    <?php if ($section === 'updates'): ?>

        <h1>Project Updates</h1>

        <p>
            <a href="index.php?section=updates&action=create">
                Add Project Update
            </a>
        </p>

        <?php if ($action === 'create'): ?>

            <h3>Add Project Update</h3>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>">

                <p>
                    <label>Contractor:</label><br>
                    <select name="contractor_id" required>
                        <option value="">-- Select Contractor --</option>
                        <?php foreach ($contractors as $contractor): ?>
                            <option value="<?= (int) $contractor['contractor_id'] ?>">
                                <?= htmlspecialchars(
                                    $contractor['contractor_first_name']
                                    . ' '
                                    . $contractor['contractor_last_name']
                                ) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </p>

                <p>
                    <label>Project:</label><br>
                    <select name="project_id" required>
                        <option value="">-- Select Project --</option>
                        <?php foreach ($projects as $project): ?>
                            <option value="<?= (int) $project['project_id'] ?>">
                                <?= htmlspecialchars(
                                    $project['project_title']
                                    . ' - '
                                    . $project['project_manager']
                                ) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </p>

                <p>
                    <label>Due Date:</label><br>
                    <input
                        type="date"
                        name="update_due_date"
                        min="<?= date('Y-m-d') ?>"
                        required
                    >
                </p>

                <button type="submit">Submit</button>
                <a href="index.php?section=updates">Cancel</a>
            </form>

        <?php else: ?>

            <table border="1" cellpadding="8">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Contractor</th>
                        <th>Project</th>
                        <th>Update Date</th>
                        <th>Due Date</th>
                        <th>Completion Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($updateRecords as $update): ?>
                        <tr>
                            <td><?= htmlspecialchars($update['update_id']) ?></td>
                            <td>
                                <?= htmlspecialchars(
                                    $update['contractor_first_name']
                                    . ' '
                                    . $update['contractor_last_name']
                                ) ?>
                            </td>
                            <td>
                                <?= htmlspecialchars($update['project_title']) ?>
                                -
                                <?= htmlspecialchars($update['project_manager']) ?>
                            </td>
                            <td><?= htmlspecialchars($update['update_date']) ?></td>
                            <td>
                                <?php
                                $today   = new DateTime();
                                $dueDate = new DateTime($update['update_due_date']);

                                if (
                                    empty($update['update_completion_date']) &&
                                    $dueDate < $today
                                ) {
                                    $daysOverdue = $today->diff($dueDate)->days;
                                    echo '<strong style="color: red;">Overdue by '
                                        . $daysOverdue
                                        . ' day(s)</strong>';
                                } else {
                                    echo htmlspecialchars($update['update_due_date']);
                                }
                                ?>
                            </td>
                            <td>
                                <?php if ($update['update_completion_date']): ?>
                                    <?= htmlspecialchars($update['update_completion_date']) ?>
                                <?php else: ?>
                                    Not completed
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($update['update_completion_date']): ?>
                                    Completed
                                <?php else: ?>
                                    In Progress
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!$update['update_completion_date']): ?>
                                    <a
                                        href="index.php?section=updates&action=complete&id=<?= (int) $update['update_id'] ?>"
                                        onclick="return confirm('Mark this update as completed?');"
                                    >
                                        Complete
                                    </a>
                                    |
                                <?php endif; ?>
                                <a
                                    href="index.php?section=updates&action=delete&id=<?= (int) $update['update_id'] ?>"
                                    onclick="return confirm('Delete this update record?');"
                                >
                                    Delete
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

        <?php endif; ?>

    <?php endif; ?>

</body>

<?php if (isset($_SESSION['alert'])): ?>
    <script>
        alert(<?= json_encode($_SESSION['alert']) ?>);
    </script>
    <?php unset($_SESSION['alert']); ?>
<?php endif; ?>

</html>