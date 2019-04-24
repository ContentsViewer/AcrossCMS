<?php

require_once dirname(__FILE__) . "/ConMAS.php";
require_once dirname(__FILE__) . "/Module/ContentsDatabaseManager.php";
require_once dirname(__FILE__) . "/Module/OutlineText.php";
require_once dirname(__FILE__) . "/Module/ContentsViewerUtil.php";
require_once dirname(__FILE__) . "/Module/Stopwatch.php";
require_once dirname(__FILE__) . "/Module/Debug.php";
require_once dirname(__FILE__) . "/Module/CacheManager.php";

OutlineText\Parser::Init();

$rootContentPath = ContentsDatabaseManager::DefalutRootContentPath();

$parentsMaxCount = 3;
$brotherTitleMaxStrWidth = 40;

$plainTextMode = false;

$warningMessages = [];

$contentPath = $rootContentPath;
if (isset($_GET['content'])) {
    $contentPath = urldecode($_GET['content']);
}

if (isset($_GET['plainText'])) {
    $plainTextMode = true;
}

// if(isset($_GET['warning'])){
//     if($_GET['warning'] == 'old-url'){
//         $warningMessages[] = '古いURLでアクセスされました(現在のURLは最新です).<br>今後のアップデートでアクセス元のリンクが切れる可能性があります.';
//     }
// }

$currentContent = new Content();
$parents = [];
$children = [];
$leftContent = null;
$rightContent = null;
$htmlConvertTime = 0;
$pageBuildTime = 0;
$useCacheCheckList = ['parser' => false, 'navigator' => false];
$cacheUpdated = false;
$cache = [];

$stopwatch = new Stopwatch();

// コンテンツの取得
$isGetCurrentContent = $currentContent->SetContent($contentPath);

$rootContentPath = ContentsDatabaseManager::GetRelatedRootFile($contentPath);
$tagMapMetaFileName = ContentsDatabaseManager::GetRelatedTagMapMetaFileName($contentPath);

if ($isGetCurrentContent && !$plainTextMode) {
    // コンテンツの設定

    $stopwatch->Start();

    // キャッシュの読み込み
    if (CacheManager::CacheExists($currentContent->Path())) {
        $cache = CacheManager::ReadCache($currentContent->Path());
    }

    if (!is_null($cache) && (CacheManager::GetCacheDate($currentContent->Path()) >= $currentContent->UpdatedAtTimestamp())
        && array_key_exists('summary', $cache) && array_key_exists('body', $cache)) {

        $currentContent->SetSummary($cache['summary']);
        $currentContent->SetBody($cache['body']);
        $useCacheCheckList['parser'] = true;
    } else {
        $context = new OutlineText\Context();
        $context->pathMacros = ContentsDatabaseManager::CreatePathMacros($currentContent->Path());

        // CurrentContentのSummaryとBodyをDecode
        $currentContent->SetSummary(OutlineText\Parser::Parse($currentContent->Summary(), $context));
        $currentContent->SetBody(OutlineText\Parser::Parse($currentContent->Body(), $context));

        $cache['summary'] = $currentContent->Summary();
        $cache['body'] = $currentContent->Body();
        $cacheUpdated = true;
    }

    $htmlConvertTime = $stopwatch->Elapsed();
    $stopwatch->Restart();

    // ChildContentsの取得
    $childrenPathList = $currentContent->ChildPathList();
    $childrenPathListCount = count($childrenPathList);
    for ($i = 0; $i < $childrenPathListCount; $i++) {
        $child = $currentContent->Child($i);
        if ($child !== false) {
            $children[] = $child;
        }
    }

    // Parentsの取得
    $parent = $currentContent->Parent();

    for ($i = 0; $i < $parentsMaxCount; $i++) {
        if ($parent === false) {
            break;
        }
        $parents[] = $parent;
        $parent = $parent->Parent();
    }

    //LeftContent, RightContentの取得
    if (isset($parents[0])) {
        $parent = $parents[0];
        $brothers = $parent->ChildPathList();
        $myIndex = $currentContent->ChildIndex();

        if ($myIndex >= 0) {
            if ($myIndex > 0) {
                $leftContent = $parent->Child($myIndex - 1);
            }
            if ($myIndex < count($brothers) - 1) {
                $rightContent = $parent->Child($myIndex + 1);
            }
        }
    }
}

if (!$isGetCurrentContent) {
    header("HTTP/1.1 404 Not Found");
}

// 権限確認
$isAuthorized = true;
$isPublicContent = true;
if ($isGetCurrentContent) {
    $authInfo = GetContentAuthInfo($currentContent->Path());
    $isAuthorized = $authInfo['isAuthorized'];
    $isPublicContent = $authInfo['isPublicContent'];
}

if (!$isAuthorized) {
    header("HTTP/1.1 401 Unauthorized");
}

if ($isAuthorized && $plainTextMode && $isGetCurrentContent) {
    echo '<!DOCTYPE html><html lang="ja"><head></head><body>';
    echo '<pre style="white-space: pre; font-family: Consolas,Liberation Mono,Courier,monospace; font-size: 12px;">';
    echo htmlspecialchars(file_get_contents(Content::RealPath($contentPath)));
    echo '</pre>';
    echo '</body></html>';
    exit();
}

?>


<!DOCTYPE html>
<html lang="ja">

<head>
    <?php readfile("Client/Common/CommonHead.html");?>

    <link rel="shortcut icon" href="Client/Common/favicon.ico" type="image/vnd.microsoft.icon" />


    <!-- Code表記 -->
    <script type="text/javascript" src="Client/syntaxhighlighter/scripts/shCore.js"></script>
    <script type="text/javascript" src="Client/syntaxhighlighter/scripts/shBrushCpp.js"></script>
    <script type="text/javascript" src="Client/syntaxhighlighter/scripts/shBrushCSharp.js"></script>
    <script type="text/javascript" src="Client/syntaxhighlighter/scripts/shBrushXml.js"></script>
    <script type="text/javascript" src="Client/syntaxhighlighter/scripts/shBrushPhp.js"></script>
    <script type="text/javascript" src="Client/syntaxhighlighter/scripts/shBrushPython.js"></script>
    <script type="text/javascript" src="Client/syntaxhighlighter/scripts/shBrushJava.js"></script>
    <script type="text/javascript" src="Client/syntaxhighlighter/scripts/shBrushBash.js"></script>
    <link type="text/css" rel="stylesheet" href="Client/syntaxhighlighter/styles/shCoreDefault.css" />
    <script type="text/javascript">SyntaxHighlighter.defaults['gutter'] = false;SyntaxHighlighter.all();</script>


    <!-- 数式表記 -->
    <script type="text/x-mathjax-config">
    MathJax.Hub.Config({
        tex2jax: { inlineMath: [['$','$'], ["\\(","\\)"]] },
        TeX: { equationNumbers: { autoNumber: "AMS" } }
    });
    </script>
    <script type="text/javascript"
    src="https://cdnjs.cloudflare.com/ajax/libs/mathjax/2.7.5/MathJax.js?config=TeX-AMS_CHTML">
    </script>
    <meta http-equiv="X-UA-Compatible" CONTENT="IE=EmulateIE7" />


    <link rel="stylesheet" href="Client/OutlineText/OutlineTextStandardStyle.css" />
    <link rel="stylesheet" href="Client/ContentsViewer/ContentsViewerStandard.css" />
    <script type="text/javascript" src="Client/ContentsViewer/ContentsViewerStandard.js"></script>

    <?php

    if (!$isAuthorized) {
        echo '<title>Unauthorized...</title>';
    }

    if ($isAuthorized && !$isGetCurrentContent) {
        echo '<title>Not Found...</title>';
    }

    if ($isAuthorized && $isGetCurrentContent) {
        //title作成
        $title = "";
        $title .= $currentContent->Title();
        if (isset($parents[0])) {
            $title .= " | " . $parents[0]->Title();
        }
        echo '<title>' . $title . '</title>';

        if($currentContent->IsFinal() || $currentContent->IsRoot()){
            readfile("Client/Common/AdSenseHead.html");
        }
    }
    ?>

</head>

<body>
    <?php
    
    echo CreateHeaderArea($rootContentPath, $tagMapMetaFileName);

    if (!$isAuthorized) {
        echo CreateUnauthorizedMessageBox();
        exit;
    }

    //CurrentContentを取得したかどうか
    if (!$isGetCurrentContent) {
    ?>
        <div id="error-message-box">
            <h1>Not Found...</h1> <br/>
            存在しない or 移動した コンテンツにアクセスした可能性があります.<br/>
            <a href="<?=CreateContentHREF($rootContentPath)?>">TopPageから探す</a>
            <div class='note'>
                * 品質向上のためこの問題は管理者に報告されます.
            </div>
        </div>

        <?php
        Debug::LogError("Not found page Accessed:\n  Content Path: {$contentPath}");
        exit;
    }

    if (!$isPublicContent) {
        echo '<div class="secret-icon">🕶</div>';
    }

    // === Title field の作成 ========================================
    $parentTitlePathList = [];
    foreach($parents as $parent){
        if($parent === false) break;
        $parentTitlePathList[] = ['title' => $parent->Title(), 'path' => CreateContentHREF($parent->Path())];
    }
    $titleField = CreateTitleField($currentContent->Title(), $parentTitlePathList);

    // === Navigator作成 =============================================

    $navigator = '';
    if (!is_null($cache)
        && (CacheManager::GetCacheDate($currentContent->Path()) >= ContentsDatabaseManager::GetContentsLinkLastUpdatedTime($currentContent->Path()))
        && array_key_exists('navigator', $cache)) {
        $navigator = $cache['navigator'];
        $useCacheCheckList['navigator'] = true;
    } else {
        
        $navigator = "<nav class='navi'><ul>";
        CreateNavHelper($parents, count($parents) - 1, $currentContent, $children, $navigator);
        $navigator .= '</ul></nav>';

        $cache['navigator'] = $navigator;
        $cacheUpdated = true;
    }

    $leftRightContentLinkContainer = '<div class="left-right-content-link-container clear-fix">';
    // === Left Brother Area ========================================
    if (!is_null($leftContent)) {

        if ($leftContent !== false) {
            $leftRightContentLinkContainer .= '<a class="left-content-link" href ="' . CreateContentHREF($leftContent->Path()) . '">'
                .'<svg viewBox="0 0 48 48"><path d="M30.83 32.67l-9.17-9.17 9.17-9.17L28 11.5l-12 12 12 12z"></path></svg>'
                . mb_strimwidth($leftContent->Title(), 0, $brotherTitleMaxStrWidth, "...", "UTF-8")
                . '</a>';
        }
    }
    // === Right Brother Area ========================================
    if (!is_null($rightContent)) {

        if ($rightContent !== false) {
            $leftRightContentLinkContainer .= '<a class="right-content-link"  href ="' . CreateContentHREF($rightContent->Path()) . '">'
                . mb_strimwidth($rightContent->Title(), 0, $brotherTitleMaxStrWidth, "...", "UTF-8")
                . '<svg viewBox="0 0 48 48"><path d="M17.17 32.92l9.17-9.17-9.17-9.17L20 11.75l12 12-12 12z"></path></svg>'
                . '</a>';
        }
    }
    $leftRightContentLinkContainer .= '</div>';
?>
    <div class='menu-open-button-wrapper'>
        <input type="checkbox" href="#" class="menu-open" name="menu-open" id="menu-open" onchange="OnChangeMenuOpen(this)"/>
        <label class="menu-open-button" for="menu-open">
        <span class="lines line-1"></span>
        <span class="lines line-2"></span>
        <span class="lines line-3"></span>
        </label>
    </div>
<?php
    echo '<div id="left-side-area-responsive">' . $navigator . '</div>';

    // === Left Side Area ============================================
    echo '<div id="left-side-area">' . $navigator . '</div>';

    // === Right Side Area ===========================================
    ?>
    <div id = 'right-side-area'>
        Index
        <nav class='navi'></nav>
        <a href='<?=CreateHREFForPlainTextMode()?>'>このページのソースコードを表示</a>
    </div>
    <?php
    // === Main Area =================================================
    echo '<div id="main-area">';

    // 最終更新欄
    ?>
        <div class="file-date-field">
            <img src='Client/Common/CreatedAtStampA.png' alt='公開日'>: <?=$currentContent->CreatedAt()?>
            <img src='Client/Common/UpdatedAtStampA.png' alt='更新日'>: <?=$currentContent->UpdatedAt()?>
        </div>
        
        <?php
    echo $titleField;
    
    echo "<ul class='tag-links'>";
    foreach ($currentContent->Tags() as $name) {
        echo "<li><a href='" . CreateTagDetailHREF($name, $tagMapMetaFileName) . "'>" . $name . "</a></li>";
    }
    echo "</ul>";

    // 概要欄
    echo '<div id="summary-field" class="summary">';
    echo $currentContent->Summary();

    if ($currentContent->IsRoot()) {
        ContentsDatabaseManager::LoadRelatedTagMap($contentPath);
        $tagMap = Content::GlobalTagMap();
        echo CreateNewBox($tagMap);

        echo "<h2>タグ一覧</h2>";
        echo CreateTagListElement($tagMap, $tagMapMetaFileName);
    }
    echo '</div>';

    // 目次欄(小画面で表示される)
    echo '<div id="index-area-on-small-screen">Index</div>';

    // 本編
    echo '<div id="main-content-field" class="main-content">' . $currentContent->Body() . '</div>';

    // --- 子コンテンツ
    echo '<div id="children-field">';
    $childrenCount = count($children);
    for ($i = 0; $i < $childrenCount; $i++) {
        echo '<div style="width:100%; display: table"><div style="display: table-cell">';
        echo '<a class="link-block-button" href ="' . CreateContentHREF($children[$i]->Path()) . '">';
        echo $children[$i]->Title() . '</a></div></div>';
    }
    echo '</div>';
    // End 子コンテンツ ---

    echo $leftRightContentLinkContainer;

    // --- Bottom Of MainArea On Small Screen ------------------------
    echo 
        '<div id="bottom-of-main-area-on-small-screen">' . '<a href="' . CreateHREFForPlainTextMode() . '">このページのソースコードを表示</a>'
        . '</div>';

    echo '</div>';
    // End Main Area ===


    $stopwatch->Stop();
    $pageBuildTime = $stopwatch->Elapsed();

    ?>

    <div id='footer'>
        <a href='./login.php' target="_blank">Manage</a>    <a href='./content-editor.php?content=<?=$currentContent->Path()?>'>Edit</a><br/>
        <b>ConMAS 2019.</b> HTML Convert Time: <?=sprintf("%.2f[ms]", $htmlConvertTime * 1000);?>;
        Page Build Time: <?=sprintf("%.2f[ms]", $pageBuildTime * 1000);?>;
        From Cache: Parser=<?=$useCacheCheckList['parser'] ? 'Y' : 'N'?>,
        Navigator=<?=$useCacheCheckList['navigator'] ? 'Y' : 'N'?>
    </div>
    
    <?php
    // $warningMessages[] = "現在メンテナンス中です...<br>動作に問題が出る可能性があります. m(_ _)m";

    if ($htmlConvertTime + $pageBuildTime > 1.0) {
        Debug::LogWarning("Performance Note:\n  HtmlConverTime: {$htmlConvertTime}[s];\n  UseCacheCheckList: Parser={$useCacheCheckList['navigator']}, Navigator={$useCacheCheckList['navigator']};\n  PageBuildTime: {$pageBuildTime}[s];\n  Page Title: {$currentContent->Title()};\n  Page Path: {$currentContent->Path()}");
        $warningMessages[] = "申し訳ございません m(. .)m<br> ページの生成に時間がかかったようです.<br>品質向上のためこの問題は管理者に報告されます.";
    }

    if (count($warningMessages) !== 0) {
        echo '<div id="warning-message-box"><ul>';

        foreach ($warningMessages as $message) {
            echo '<li>' . $message . '</li>';
        }
        echo '</ul></div>';
    }
    ?>
</body>
</html>

<?php
if ($cacheUpdated) {
    CacheManager::WriteCache($currentContent->Path(), $cache);
}

function CreateHREFForPlainTextMode()
{
    $query = $_SERVER["QUERY_STRING"] . "&plainText";

    return "?" . $query;
}

function CreateNavHelper($parents, $parentsIndex, $currentContent, $children, &$navigator)
{
    if ($parentsIndex < 0) {
        // echo '1+';
        $navigator .= '<li><a class = "selected" href="' . CreateContentHREF($currentContent->Path()) . '">' . $currentContent->Title() . '</a></li>';

        $navigator .= "<ul>";
        foreach ($children as $c) {

            $navigator .= '<li><a href="' . CreateContentHREF($c->Path()) . '">' . $c->Title() . '</a></li>';
        }

        $navigator .= "</ul>";

        return;
    }

    $childrenCount = $parents[$parentsIndex]->ChildCount();

    $navigator .= '<li><a class = "selected" href="' . CreateContentHREF($parents[$parentsIndex]->Path()) . '">' . $parents[$parentsIndex]->Title() . '</a></li>';

    $navigator .= "<ul>";
    if ($parentsIndex == 0) {
        // echo '2+';
        $currentContentIndex = $currentContent->ChildIndex();
        for ($i = 0; $i < $childrenCount; $i++) {

            $child = $parents[$parentsIndex]->Child($i);
            if ($child === false) {
                continue;
            }

            if ($i == $currentContentIndex) {
                $navigator .= '<li><a class = "selected" href="' . CreateContentHREF($child->Path()) . '">' . $child->Title() . '</a></li>';

                $navigator .= "<ul>";
                foreach ($children as $c) {
                    $navigator .= '<li><a href="' . CreateContentHREF($c->Path()) . '">' . $c->Title() . '</a></li>';
                }
                $navigator .= "</ul>";
            } else {
                $navigator .= '<li><a href="' . CreateContentHREF($child->Path()) . '">' . $child->Title() . '</a></li>';
            }
        }
    } else {
        // echo '3+';
        $nextParentIndex = $parents[$parentsIndex - 1]->ChildIndex();
        for ($i = 0; $i < $childrenCount; $i++) {
            if ($i == $nextParentIndex) {
                CreateNavHelper($parents, $parentsIndex - 1, $currentContent, $children, $navigator);
            } else {
                $child = $parents[$parentsIndex]->Child($i);
                if ($child === false) {
                    continue;
                }
                $navigator .= '<li><a href="' . CreateContentHREF($child->Path()) . '">' . $child->Title() . '</a></li>';
            }
        }
    }
    $navigator .= "</ul>";
    return;
}
?>