<?php
// Composer 로드: Composer로 설치된 라이브러리들을 사용할 수 있게 합니다.
require_once 'vendor/autoload.php';

// 환경변수 사용: .env 파일에서 환경 변수를 로드합니다.
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// 데이터베이스 설정: 환경 변수에서 가져온 정보를 사용해 데이터베이스 연결 정보를 설정합니다.

// 리팩토링 전 (주석 처리)
/*
$servername = "localhost";
$username = "root";
$password = $_ENV['rootpassword'];  // MySQL root 비밀번호
$dbname = "anonymous_board";
$super_password = $_ENV['supersecret']; // 슈퍼 계정 비밀번호
*/

// 리팩토링 후
$dbConfig = [
    'servername' => 'localhost',
    'username' => 'root',
    'password' => $_ENV['rootpassword'],
    'dbname' => 'anonymous_board'
];
$super_password = $_ENV['supersecret']; // 슈퍼 계정 비밀번호

// extract 함수 사용: $dbConfig 배열의 키를 각각의 변수로 추출합니다.
extract($dbConfig);

// 데이터베이스 연결: 설정된 정보를 사용해 MySQL 데이터베이스에 연결합니다.

// 리팩토링 전 (주석 처리)
/*
$conn = new mysqli($servername, $username, $password, $dbname);
*/

// 리팩토링 후 (extract 사용 후 연결)
$conn = new mysqli($servername, $username, $password, $dbname);

// 연결 확인: 데이터베이스 연결에 실패하면 에러 메시지를 출력하고 종료합니다.
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
    // die: 에러 발생 시 에러 원인을 출력하고 스크립트를 종료합니다.
}

// 게시물 저장 함수: 게시물 내용과 비밀번호를 받아 데이터베이스에 저장하는 함수입니다.
function savePost($conn, $post, $password) {
    $stmt = $conn->prepare("INSERT INTO posts (content, password) VALUES (?, ?)");
    $stmt->bind_param("ss", $post, $password);
    $stmt->execute();
    $stmt->close();
}

// 게시물 삭제 함수: 게시물 ID와 비밀번호를 받아 삭제를 처리하는 함수입니다.
// 게시물 삭제 함수: 게시물 ID와 비밀번호를 받아 삭제를 처리하는 함수입니다.
function deletePost($conn, $post_id, $password) {
    global $super_password;

    // 게시물의 비밀번호 가져오기
    $stmt = $conn->prepare("SELECT password FROM posts WHERE id = ?");
    $stmt->bind_param("i", $post_id);
    $stmt->execute();

    // 변수 초기화
    $hashed_password = null;

    $stmt->bind_result($hashed_password);
    
    if ($stmt->fetch() && $hashed_password !== null) {
        $stmt->close();

        // 비밀번호 확인
        if (password_verify($password, $hashed_password) || $password === $super_password) {
            // 비밀번호가 일치하거나 슈퍼 비밀번호인 경우, 게시물 삭제
            $stmt = $conn->prepare("DELETE FROM posts WHERE id = ?");
            $stmt->bind_param("i", $post_id);
            $stmt->execute();
            $stmt->close();

            echo "<p>게시물이 성공적으로 삭제되었습니다.</p>";
        } else {
            echo "<p>비밀번호가 일치하지 않습니다.</p>";
        }
    } else {
        $stmt->close();
        echo "<p>게시물을 찾을 수 없습니다.</p>";
    }
}




// 게시물 삭제 요청 처리: 삭제 요청이 있을 경우 처리
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == '삭제') {
    if (isset($_POST['post_id']) && isset($_POST['password'])) {
        $post_id = $_POST['post_id'];
        $password = $_POST['password'];
        deletePost($conn, $post_id, $password);

        // PRG 패턴: Post/Redirect/Get 패턴을 활용하여 폼이 다시 제출되는 것을 방지합니다.
        header("Location: index.php");
        exit();
    }
}

// 게시물 저장 요청 처리: POST 요청이 들어오면 게시물과 비밀번호를 받아 저장합니다.
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['post']) && isset($_POST['password'])) {
    $post = $_POST['post'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    savePost($conn, $post, $password);

    // PRG 패턴: Post/Redirect/Get 패턴을 활용하여 폼이 다시 제출되는 것을 방지합니다.
    header("Location: index.php");
    exit();

    // 게시물 내용과 비밀번호(해시화된)를 데이터베이스에 저장합니다.
}

// 페이지네이션 처리

// 게시물 총 개수 계산: 데이터베이스에서 게시물의 총 개수를 가져옵니다.
$result_count = $conn->query("SELECT COUNT(*) AS total FROM posts");
$row_count = $result_count->fetch_assoc();
$total_posts = $row_count['total'];

// 한 페이지에 표시할 게시물 수 설정
$limit = 5;

// 리팩토링 전 페이지네이션 계산 (주석 처리)
/*
$total_pages = ceil($total_posts / $limit);

// 현재 페이지 번호 확인: 쿼리스트링에서 가져오거나, 기본값으로 1을 설정합니다.
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

// 페이지 범위 초과 방지
if ($page < 1) {
    $page = 1;
}

// 시작 게시물 계산
$offset = ($page - 1) * $limit;
*/

// 리팩토링 후 페이지네이션 계산
function calculatePagination($total_posts, $limit, $page) {
    $total_pages = ceil($total_posts / $limit); // 총 페이지 수 계산
    $page = max($page, 1); // 페이지 번호가 1보다 작을 경우 1로 설정
    $offset = ($page - 1) * $limit; // 시작 게시물 위치 계산
    return [$total_pages, $offset];
}

// 함수 호출로 페이지네이션 계산: 함수 호출을 통해 총 페이지 수와 시작 위치를 가져옵니다.
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
list($total_pages, $offset) = calculatePagination($total_posts, $limit, $page);

// 현재 페이지에 해당하는 게시물 가져오기
$result = $conn->query("SELECT * FROM posts ORDER BY created_at DESC LIMIT $limit OFFSET $offset");

// 게시물 출력 함수: 각 게시물을 출력하고 수정 및 삭제 폼을 제공합니다.
function displayPost($row) {
    echo "<div class='post'>";
    echo "<p>" . htmlspecialchars($row['content']) . "</p>";
    echo "<small>게시일: " . $row['created_at'] . "</small>";
    
    echo "<form method='post' action='index.php' class='post-form'>";
    echo "<input type='hidden' name='post_id' value='" . $row['id'] . "'>";
    echo "<textarea name='new_content' cols='30' rows='3' class='textarea post-textarea'>" . htmlspecialchars($row['content']) . "</textarea><br>";
    echo "<label for='password'>비밀번호:</label>";
    echo "<input type='password' name='password' required class='input'><br>";
    echo "<input type='submit' name='action' value='수정' class='button'>";
    echo "<input type='submit' name='action' value='삭제' class='button'>";
    echo "</form>";

    echo "<hr>";
    echo "</div>";
}

// 리팩토링 전 (주석 처리)
/*
echo "<div>";
for ($i = 1; $i <= $total_pages; $i++) {
    if ($i == $page) {
        echo "<strong>$i</strong> ";
    } else {
        echo "<a href='index.php?page=$i'>$i</a> ";
    }
}
echo "</div>";
*/

// 리팩토링 후
function renderPagination($total_pages, $current_page) {
    echo "<div class='pagination'>";
    for ($i = 1; $i <= $total_pages; $i++) {
        if ($i == $current_page) {
            echo "<strong class='current-page'>$i</strong> "; // 현재 페이지는 강조 표시
        } else {
            echo "<a href='index.php?page=$i' class='page-link'>$i</a> "; // 다른 페이지로 이동하는 링크
        }
    }
    echo "</div>";
}

// 데이터베이스 연결 종료: 모든 작업이 끝난 후 데이터베이스 연결을 종료합니다.
$conn->close();
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>익명 게시판</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }
        .container {
            width: 60%;
            height:100%;
            margin: 50px auto;
            background-color: #fff;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
        }
        .posts {
            width:100%;
            height:400px;
            overflow-y:auto;
        }
        h1 {
            text-align: center;
            color: #333;
            margin-bottom: 30px;
        }
        form {
            margin-bottom: 40px;
            height:350px;
        }
        label {
            font-weight: bold;
            display: block;
            margin-bottom: 5px;
            color: #555;
        }
        .input, .textarea {
            width: 98%;
            padding: 10px;
            margin-bottom: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 16px;
            background-color: #f9f9f9;
            transition: border-color 0.3s, box-shadow 0.3s;
        }
        .input:focus, .textarea:focus {
            border-color: #5cb85c;
            box-shadow: 0 0 5px rgba(92, 184, 92, 0.5);
            outline: none;
        }
        .textarea {
            resize: vertical;
            min-height: 150px;
        }
        .post-textarea {
            min-height: 80px;
        }
        .button {
            background-color: #5cb85c;
            color: white;
            border: none;
            margin-right:5px;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s;
        }
        .button:hover {
            background-color: #4cae4c;
        }
        .post {
            margin-bottom: 20px;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background-color: #f9f9f9;
            box-shadow: 0 0 5px rgba(0, 0, 0, 0.1);
        }
        .post p {
            font-size: 18px;
            color: #333;
        }
        .post small {
            color: #999;
        }
        .pagination {
            text-align: center;
            margin-top: 20px;
        }
        .page-link {
            color: #333;
            padding: 8px 12px;
            text-decoration: none;
            border: 1px solid #ddd;
            margin: 0 2px;
            border-radius: 5px;
            transition: background-color 0.3s;
        }
        .page-link:hover {
            background-color: #ddd;
        }
        .current-page {
            font-weight: bold;
            color: #555;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background-color: #f0f0f0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>익명 게시판</h1>
        <form action="index.php" method="post">
            <label for="post">게시물:</label>
            <textarea name="post" id="post" cols="30" rows="10" class="textarea"></textarea>
            <label for="password">비밀번호:</label>
            <input type="password" name="password" id="password" required class="input">
            <input type="submit" value="게시하기" class="button">
        </form>

        <?php
        // 페이지네이션 링크 출력
        renderPagination($total_pages, $page);
        ?>

        <div class="posts">
            <?php
            // 게시물 출력: 데이터베이스에서 가져온 각 게시물을 출력합니다.
            if ($result->num_rows > 0) {
                while($row = $result->fetch_assoc()) {
                    displayPost($row);
                }
            } else {
                echo "<p>게시물이 없습니다.</p>";
            }
            ?>
        </div>
    </div>
</body>
</html>
