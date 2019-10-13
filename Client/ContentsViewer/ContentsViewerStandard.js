﻿
// var offsetYToHideHeader = 100;
var offsetYToHideHeader = 50;

var headerArea = null;
var pullDownMenuButton = null;
var pullUpMenuButton = null;
var leftSideAreaResponsive = null;
var menuOpenInput = null;
var indexArea = null;
var warningMessageBox = null;
var isTouchDevice = false;
var sitemask = null;
var doseHideHeader = false;
var menuOpenButton = null;

var sectionListInMainContent = [];
var sectionListInSideArea = [];
var currentSectionIdDict = {};

var timer = null;
var scrollPosPrev = 0

window.onload = function () {
	// 各Area取得
	headerArea = document.querySelector("#header-area");
	pullDownMenuButton = document.querySelector("#pull-down-menu-button");
	pullUpMenuButton = document.querySelector("#pull-up-menu-button");
	warningMessageBox = document.getElementById('warning-message-box');
	leftSideAreaResponsive = document.getElementById('left-side-area-responsive');
	menuOpenInput = document.getElementById('menu-open');
	sitemask = document.getElementById('sitemask');
	menuOpenButton = document.getElementsByClassName('menu-open-button-wrapper')[0];

	scrollPosPrev = window.pageYOffset

	// Scrollイベント登録
	window.addEventListener("scroll", OnScroll);

	// タッチデバイス判定
	isTouchDevice = IsTouchDevice();

	// --- 目次関係 --------------------------------------------
	var rightSideArea = document.getElementById("right-side-area");
	var docOutlineEmbeded = document.getElementById("doc-outline-embeded");
	var contentBody = document.getElementById("content-body");

	if (contentBody && rightSideArea) {
		// rightSideArea内にあるNaviを取得
		var navi = null;
		if (rightSideArea.getElementsByClassName("navi").length > 0) {
			var navi = rightSideArea.getElementsByClassName("navi")[0];
		}

		// Naviを取得できた場合のみ実行
		if (navi) {
			var totalID = 0;
			if (contentBody.children.length == 0 || (totalID = CreateSectionTreeHelper(contentBody, navi, 0)) == 0) {
				navi.textContent = "　ありません";
			}

			//alert(indexAreaOnSmallScreen);
			if (docOutlineEmbeded) {
				var naviEmbeded = navi.cloneNode(true);
				naviEmbeded.removeAttribute("class");
				naviEmbeded.classList.add("accshow");
				docOutlineEmbeded.appendChild(naviEmbeded);
			}
			//alert(totalID);
			//alert("1");
		}
	}

	// UpdateCurrentSectionSelection();
	OnScroll();
}

window.onresize = function () {
	CloseLeftSideArea();
}

//
// mainContent内にあるSectionを取得します.
// 同時に, ナヴィゲータの作成, sectionListInMainContent, sectionListInIndexAreaにSectionを登録します.
//
// @param element:
//  Section探索元
//  この下の階層にSectionListが来るようにしてください
//  
// @param navi:
//  生成されるナヴィゲータリスト
//  
// @param idBegin:
//  振り分け開始id
//
function CreateSectionTreeHelper(element, navi, idBegin) {
	var ulElement = document.createElement("ul");

	for (var i = 0; i < element.children.length; i++) {
		child = element.children[i];

		if (child.tagName == "H2"
			|| child.tagName == "H3"
			|| child.tagName == "H4") {

			child.setAttribute("id", "SectionID_" + idBegin);

			var section = document.createElement("li");
			var link = document.createElement("a");
			// link.innerHTML = child.innerHTML;
			link.textContent = child.textContent;
			link.href = "#SectionID_" + idBegin;
			section.appendChild(link);

			sectionListInSideArea.push(link);

			ulElement.appendChild(section);

			idBegin++;

			if (i + 1 < element.children.length
				&& element.children[i + 1].className == "section") {

				// heading + div(section) per one set.
				sectionListInMainContent.push(child);
				sectionListInMainContent.push(element.children[i + 1]);

				idBegin = CreateSectionTreeHelper(element.children[i + 1], section, idBegin);
			}
			else {
				sectionListInMainContent.push(child);
				sectionListInMainContent.push(null);
			}
		}
	}

	if (ulElement.children.length > 0) {
		navi.appendChild(ulElement);
	}
	return idBegin;
}

sumOfScroll = 0
isHiddenHeader = false
function OnScroll() {

	//一定量スクロールされたとき
	if (window.pageYOffset > offsetYToHideHeader) {
		// headerArea.classList.add('transparent');
		// headerArea.style.animationName = "fade-out";
		// headerArea.style.animationDuration = "0.8s";
		if (warningMessageBox != null) {

			warningMessageBox.style.animationName = "warning-message-box-slideout";
			warningMessageBox = null;
		}
	}

	if (sumOfScroll * (window.pageYOffset - scrollPosPrev) < 0.0) {
		sumOfScroll = 0
	}
	sumOfScroll += window.pageYOffset - scrollPosPrev
	// scroll_velocity = window.pageYOffset - scrollPosPrev

	if (window.pageYOffset < offsetYToHideHeader) {
		// headerArea.classList.remove('hide-header')
		if (isHiddenHeader) {
			headerArea.style.animationName = "appear-header-anim";
			// menuOpenButton.style.animationName = "slidedown-top-icon";
			// headerArea.style.animationName = "fade-in";
			isHiddenHeader = false;
		}
	}
	else {
		// headerArea.classList.add('hide-header')
		if (!isHiddenHeader) {
			headerArea.style.animationName = "hide-header-anim";
			// menuOpenButton.style.animationName = "slideup-top-icon";
			// headerArea.style.animationName = "fade-out";
			OnClickPullUpButton();
			isHiddenHeader = true
		}
	}

	scrollPosPrev = window.pageYOffset;

	if (timer) {
		return;
	}

	timer = setTimeout(function () {
		timer = null;
		UpdateCurrentSectionSelection();
	}, 200);
}

function UpdateCurrentSectionSelection() {
	var selectionUpdated = false;
	var updatedSectionIdDict = {}
	for (var i = 0; i < sectionListInMainContent.length; i++) {
		if (sectionListInMainContent[i] == null) {
			continue;
		}
		var sectionRect = sectionListInMainContent[i].getBoundingClientRect();
		if (sectionRect.top < window.innerHeight / 3 && sectionRect.bottom > window.innerHeight / 3) {
			if (!(i in currentSectionIdDict)) {
				selectionUpdated = true;
			}
			updatedSectionIdDict[i] = true;
		}
	}

	// selectionUpdated |= (Object.keys(currentSectionIdDict).length != Object.keys(updatedSectionIdDict).length);
	if (selectionUpdated) {
		for (var id in currentSectionIdDict) {
			sectionListInSideArea[Math.floor(id / 2)].removeAttribute("class");
		}

		for (var id in updatedSectionIdDict) {
			sectionListInSideArea[Math.floor(id / 2)].setAttribute("class", "selected");
		}

		currentSectionIdDict = updatedSectionIdDict;
	}
}

function IsTouchDevice() {
	var result = false;
	if (window.ontouchstart === null) {
		result = true;
	}
	return result;
}

function OnClickPullDownButton() {
	pullDownMenuButton.style.display = 'none';
	pullUpMenuButton.style.display = 'block';

	headerArea.classList.add('pull-down');
}

function OnClickPullUpButton() {
	pullDownMenuButton.style.display = 'block';
	pullUpMenuButton.style.display = 'none';
	headerArea.classList.remove('pull-down');
}

function OnChangeMenuOpen(input) {
	if (input.checked) {
		OpenLeftSideArea();
	}
	else {
		CloseLeftSideArea();
	}
}

function OpenLeftSideArea() {
	menuOpenInput.checked = true;
	leftSideAreaResponsive.classList.add('left-side-area-responsive-open');

	document.body.style.overflow = "hidden";
	leftSideAreaResponsive.style.zIndex = "99999";
	menuOpenButton.style.zIndex = "99999";
	sitemask.setAttribute('visible', '');
}

function CloseLeftSideArea() {
	menuOpenInput.checked = false;
	leftSideAreaResponsive.classList.remove('left-side-area-responsive-open');

	document.body.style.overflow = "auto";

	leftSideAreaResponsive.style.zIndex = "990";
	menuOpenButton.style.zIndex = "990";
	sitemask.removeAttribute('visible');
}

function OnClickSitemask() {
	CloseLeftSideArea();
}

// function OpenWindow(url, name) {
// 	win = window.open(url, name);

// 	// return;
// 	// /* ウィンドウオブジェトを格納する変数 */
// 	// var win;
// 	// /* ウィンドウの存在確認をしてからウィンドウを開く */
// 	// if (!win || win.closed) {
// 	// 	/*
// 	// 	ウィンドウオブジェクトを格納した変数が存在しない、
// 	// 	ウィンドウが存在しない、ウィンドウが閉じられている
// 	// 	場合は、新ウィンドウを開く。
// 	// 	*/
// 	// 	win = window.open(url, name);
// 	// } else {
// 	// 	/* 
// 	// 	既にウィンドウが開かれている場合は
// 	// 	そのウィンドウにフォーカスを当てる。
// 	// 	*/
// 	// 	win.focus();
// 	// }
// }