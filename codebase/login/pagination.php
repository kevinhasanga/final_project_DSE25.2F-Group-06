<?php
// Shared list-pagination helper, used by every role's record-list pages.
// $paramName lets a single page host more than one independently paginated
// list (e.g. approvals.php has separate budget/purchase/discount/expansion
// tables) by giving each list its own query-string key instead of "page".

function getCurrentPage($paramName = "page")
{
    $page = isset($_GET[$paramName]) ? (int) $_GET[$paramName] : 1;
    return $page > 0 ? $page : 1;
}

function countRows($connection, $countSql, $paramTypes = "", $params = [])
{
    if ($paramTypes === "") {
        $result = mysqli_query($connection, $countSql);
        $row = mysqli_fetch_row($result);
        return (int) $row[0];
    }

    $statement = mysqli_prepare($connection, $countSql);
    mysqli_stmt_bind_param($statement, $paramTypes, ...$params);
    mysqli_stmt_execute($statement);
    $row = mysqli_fetch_row(mysqli_stmt_get_result($statement));
    mysqli_stmt_close($statement);
    return (int) $row[0];
}

// Renders a "Previous / Page X of Y / Next" bar. $page is clamped to
// [1, totalPages] by the caller before running the LIMIT/OFFSET query.
function renderPagination($page, $totalRows, $perPage = 10, $paramName = "page")
{
    $totalPages = max(1, (int) ceil($totalRows / $perPage));
    if ($totalPages <= 1) {
        return;
    }

    $queryParams = $_GET;
    $linkFor = function ($targetPage) use ($queryParams, $paramName) {
        $queryParams[$paramName] = $targetPage;
        return "?" . http_build_query($queryParams);
    };

    echo '<div style="display: flex; align-items: center; gap: 12px; padding: 16px 20px;">';
    if ($page > 1) {
        echo '<a class="btn secondary" href="' . htmlspecialchars($linkFor($page - 1)) . '">Previous</a>';
    } else {
        echo '<span class="btn secondary" style="opacity: 0.5; pointer-events: none;">Previous</span>';
    }
    echo '<span style="font-size: 14px; color: #6f7f95;">Page ' . $page . ' of ' . $totalPages . ' (' . $totalRows . ' records)</span>';
    if ($page < $totalPages) {
        echo '<a class="btn secondary" href="' . htmlspecialchars($linkFor($page + 1)) . '">Next</a>';
    } else {
        echo '<span class="btn secondary" style="opacity: 0.5; pointer-events: none;">Next</span>';
    }
    echo '</div>';
}
