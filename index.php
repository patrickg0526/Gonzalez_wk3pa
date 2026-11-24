<?php
/*
 * Program:  Gonzalez_wk3pa - index.php
 * Author:   Patrick Gonzalez
 * Course:   SDC310 Server-Side Scripting with PHP
 * Purpose:  Week 3 Performance Assessment. A single-page PHP web application
 *           that connects to the sdc310_wk3pa MySQL database and performs
 *           CRUD operations on the personal_info table:
 *             - Read:   displays every stored record in a table
 *             - Create: adds new personal information from the form
 *             - Update: loads a record into the form (Edit) and saves changes
 *             - Delete: removes the selected record
 * Input:    Form fields sent with POST: user_no, full_name, birthdate,
 *           favorite_color, favorite_place, nickname, and one action button
 *           (add, edit, update, delete, or cancel).
 * Output:   An HTML table of all records, a status message, and the entry form.
 */

// ---------------------------------------------------------------------------
// Database connection settings (ecpi_user account from the course SQL script)
// ---------------------------------------------------------------------------
$hostname = "localhost";
$username = "ecpi_user";
$password = "Password1";
$dbname   = "sdc310_wk3pa";

// Turn mysqli errors into exceptions so a failed query stops with a clear message.
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = mysqli_connect($hostname, $username, $password, $dbname);
    mysqli_set_charset($conn, "utf8mb4");
} catch (mysqli_sql_exception $e) {
    die("<p>Unable to connect to the sdc310_wk3pa database. Make sure MySQL is running.</p>");
}

// ---------------------------------------------------------------------------
// Form field variables. They start empty so the form is blank on first load.
// $userNo is -1 when no existing record is selected.
// ---------------------------------------------------------------------------
$userNo        = -1;
$fullName      = "";
$birthdate     = "";
$favoriteColor = "";
$favoritePlace = "";
$nickname      = "";

$message   = "";    // status message shown above the form after an action
$errors    = [];    // validation errors for add/update
$isEditing = false; // true when a record is loaded into the form for editing

/*
 * Function: read_form_fields
 * Purpose:  Reads the five text fields from $_POST and trims extra spaces.
 * Returns:  An associative array with keys full_name, birthdate,
 *           favorite_color, favorite_place, and nickname.
 */
function read_form_fields()
{
    $fields = ["full_name", "birthdate", "favorite_color", "favorite_place", "nickname"];
    $values = [];
    foreach ($fields as $field) {
        $values[$field] = trim($_POST[$field] ?? "");
    }
    return $values;
}

/*
 * Function: validate_fields
 * Purpose:  Checks that every field has a value and fits the column size
 *           defined in the personal_info table.
 * Params:   $values - array returned by read_form_fields()
 * Returns:  An array of error messages (empty when everything is valid).
 */
function validate_fields($values)
{
    // label and maximum length for each column in personal_info
    $rules = [
        "full_name"      => ["Name", 50],
        "birthdate"      => ["Date of Birth", 25],
        "favorite_color" => ["Favorite Color", 50],
        "favorite_place" => ["Favorite Place To Visit", 50],
        "nickname"       => ["Nickname", 25],
    ];
    $errors = [];
    foreach ($rules as $field => [$label, $maxLength]) {
        if ($values[$field] === "") {
            $errors[] = "Please enter your $label.";
        } elseif (strlen($values[$field]) > $maxLength) {
            $errors[] = "$label must be $maxLength characters or fewer.";
        }
    }
    return $errors;
}

/*
 * Function: h
 * Purpose:  Escapes a value for safe display in HTML so user input is shown
 *           as text and never run as HTML or script.
 */
function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

// ---------------------------------------------------------------------------
// Process the button that was clicked (only when the form was posted).
// All SQL that uses user input is run as a prepared statement.
// ---------------------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $userNo = (int) ($_POST["user_no"] ?? -1);

    if (isset($_POST["add"])) {
        // CREATE: insert a new record
        $values = read_form_fields();
        $errors = validate_fields($values);
        if (empty($errors)) {
            $stmt = mysqli_prepare($conn,
                "INSERT INTO personal_info (FullName, Birthdate, FavoriteColor, FavoritePlace, Nickname)
                 VALUES (?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "sssss", $values["full_name"], $values["birthdate"],
                $values["favorite_color"], $values["favorite_place"], $values["nickname"]);
            mysqli_stmt_execute($stmt);
            $message = "Added " . h($values["full_name"]) . " (record #" . mysqli_insert_id($conn) . ").";
            $userNo = -1;
        } else {
            // keep what the user typed so they can fix it
            [$fullName, $birthdate, $favoriteColor, $favoritePlace, $nickname] = array_values($values);
        }
    } elseif (isset($_POST["edit"])) {
        // READ one record: load it into the form for editing
        $stmt = mysqli_prepare($conn, "SELECT * FROM personal_info WHERE UserNo = ?");
        mysqli_stmt_bind_param($stmt, "i", $userNo);
        mysqli_stmt_execute($stmt);
        $record = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        if ($record) {
            $fullName      = $record["FullName"];
            $birthdate     = $record["Birthdate"];
            $favoriteColor = $record["FavoriteColor"];
            $favoritePlace = $record["FavoritePlace"];
            $nickname      = $record["Nickname"];
            $isEditing     = true;
            $message       = "Editing record #$userNo. Change the fields and click Update.";
        } else {
            $message = "That record no longer exists.";
            $userNo  = -1;
        }
    } elseif (isset($_POST["update"])) {
        // UPDATE: save changes to the selected record
        $values = read_form_fields();
        $errors = validate_fields($values);
        if (empty($errors)) {
            $stmt = mysqli_prepare($conn,
                "UPDATE personal_info
                 SET FullName = ?, Birthdate = ?, FavoriteColor = ?, FavoritePlace = ?, Nickname = ?
                 WHERE UserNo = ?");
            mysqli_stmt_bind_param($stmt, "sssssi", $values["full_name"], $values["birthdate"],
                $values["favorite_color"], $values["favorite_place"], $values["nickname"], $userNo);
            mysqli_stmt_execute($stmt);
            $message = "Updated record #$userNo.";
            $userNo = -1;
        } else {
            // stay in edit mode with the user's changes so they can fix them
            [$fullName, $birthdate, $favoriteColor, $favoritePlace, $nickname] = array_values($values);
            $isEditing = true;
        }
    } elseif (isset($_POST["delete"])) {
        // DELETE: remove the selected record
        $stmt = mysqli_prepare($conn, "DELETE FROM personal_info WHERE UserNo = ?");
        mysqli_stmt_bind_param($stmt, "i", $userNo);
        mysqli_stmt_execute($stmt);
        $message = "Deleted record #$userNo.";
        $userNo = -1;
    } else {
        // Cancel (or anything else): return the form to Add mode
        $userNo = -1;
    }
}

// ---------------------------------------------------------------------------
// READ all records for the table (runs on every page load).
// ---------------------------------------------------------------------------
$result = mysqli_query($conn, "SELECT * FROM personal_info ORDER BY UserNo");
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Patrick Gonzalez Wk 3 Performance Assessment</title>
    <style>
        body    { font-family: Arial, Helvetica, sans-serif; margin: 30px; }
        table   { border-collapse: collapse; margin-bottom: 25px; }
        th, td  { border: 1px solid #555; padding: 8px 12px; text-align: left; }
        th      { background-color: #1f4e79; color: #fff; }
        tr:nth-child(even) td { background-color: #f2f2f2; }
        td form { margin: 0; }
        .field  { margin-bottom: 10px; }
        label   { display: inline-block; width: 210px; font-weight: bold; }
        input[type="text"] { width: 260px; padding: 4px; }
        .message { color: #1b5e20; font-weight: bold; }
        .error   { color: #b71c1c; }
    </style>
</head>

<body>
    <h1>Patrick Gonzalez Wk 3 Performance Assessment</h1>

    <!-- READ: every record in the personal_info table -->
    <h2>Stored Personal Information</h2>
    <table>
        <tr>
            <th>User #</th>
            <th>Name</th>
            <th>Date of Birth</th>
            <th>Favorite Color</th>
            <th>Favorite Place To Visit</th>
            <th>Nickname</th>
            <th></th>
            <th></th>
        </tr>
        <?php while ($row = mysqli_fetch_assoc($result)) : ?>
            <tr>
                <td><?php echo h($row["UserNo"]); ?></td>
                <td><?php echo h($row["FullName"]); ?></td>
                <td><?php echo h($row["Birthdate"]); ?></td>
                <td><?php echo h($row["FavoriteColor"]); ?></td>
                <td><?php echo h($row["FavoritePlace"]); ?></td>
                <td><?php echo h($row["Nickname"]); ?></td>
                <td>
                    <!-- Edit loads this record into the form below -->
                    <form method="POST">
                        <input type="hidden" name="user_no" value="<?php echo h($row["UserNo"]); ?>">
                        <input type="submit" name="edit" value="Edit">
                    </form>
                </td>
                <td>
                    <!-- Delete removes this record -->
                    <form method="POST">
                        <input type="hidden" name="user_no" value="<?php echo h($row["UserNo"]); ?>">
                        <input type="submit" name="delete" value="Delete">
                    </form>
                </td>
            </tr>
        <?php endwhile; ?>
    </table>

    <?php if ($message !== "") : ?>
        <p class="message"><?php echo $message; ?></p>
    <?php endif; ?>
    <?php foreach ($errors as $error) : ?>
        <p class="error"><?php echo h($error); ?></p>
    <?php endforeach; ?>

    <!-- CREATE / UPDATE form: shows Add in normal mode, Update + Cancel in edit mode -->
    <h2><?php echo $isEditing ? "Update Personal Information" : "Add Personal Information"; ?></h2>
    <form method="POST">
        <input type="hidden" name="user_no" value="<?php echo h($userNo); ?>">
        <div class="field">
            <label for="full_name">Name:</label>
            <input type="text" id="full_name" name="full_name" maxlength="50" value="<?php echo h($fullName); ?>">
        </div>
        <div class="field">
            <label for="birthdate">Date of Birth:</label>
            <input type="text" id="birthdate" name="birthdate" maxlength="25" placeholder="MM/DD/YYYY"
                value="<?php echo h($birthdate); ?>">
        </div>
        <div class="field">
            <label for="favorite_color">Favorite Color:</label>
            <input type="text" id="favorite_color" name="favorite_color" maxlength="50"
                value="<?php echo h($favoriteColor); ?>">
        </div>
        <div class="field">
            <label for="favorite_place">Favorite Place To Visit:</label>
            <input type="text" id="favorite_place" name="favorite_place" maxlength="50"
                value="<?php echo h($favoritePlace); ?>">
        </div>
        <div class="field">
            <label for="nickname">Nickname:</label>
            <input type="text" id="nickname" name="nickname" maxlength="25" value="<?php echo h($nickname); ?>">
        </div>
        <?php if ($isEditing) : ?>
            <input type="submit" name="update" value="Update">
            <input type="submit" name="cancel" value="Cancel">
        <?php else : ?>
            <input type="submit" name="add" value="Add">
        <?php endif; ?>
    </form>
</body>

</html>
<?php
// Close the database connection when the page is finished.
mysqli_close($conn);
?>
