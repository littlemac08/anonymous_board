<?php
$servername = "localhost";
$username = "root";
$password = "mac1234!";  // MySQL root 비밀번호
$dbname = "anonymous_board";

// 데이터베이스 연결
$conn = new mysqli($servername, $username, $password, $dbname);

// 연결 확인
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// POST 요청이 있을 때 데이터베이스에 저장
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $post = $_POST['post'];

    // SQL 쿼리 준비 및 실행
    $stmt = $conn->prepare("INSERT INTO posts (content) VALUES (?)");
    $stmt->bind_param("s", $post);
    $stmt->execute();
    $stmt->close();

    echo "<p>게시물이 저장되었습니다.</p>";
}

// 데이터베이스 연결 종료
$conn->close();
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>익명 게시판</title>
</head>
<body>
    <h1>익명 게시판</h1>
    <form action="index.php" method="post">
        <label for="post">게시물:</label><br>
        <textarea name="post" id="post" cols="30" rows="10"></textarea><br><br>
        <input type="submit" value="게시하기">
    </form>
</body>
</html>