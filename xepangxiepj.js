
    // 显示评价弹窗
function showAppraise() {
    document.getElementById('appraise-overlay').classList.add('active');
    document.getElementById('appraise').classList.add('active');
}

// 关闭评价弹窗
function closeAppraise() {
    document.getElementById('appraise-overlay').classList.remove('active');
    document.getElementById('appraise').classList.remove('active');
}

// 提交评价
function submitAppraise() {
    closeAppraise();
    document.getElementById('cgtipstext').innerHTML = "评价成功";
    if (document.getElementById('cgtips')) {
        document.getElementById('cgtips').style.display = 'flex';
        setTimeout(function() {
            document.getElementById('cgtips').style.display = 'none';
        }, 1500);
    }
}

function emoji1() {
    document.getElementById('emoji1').src = '/assets/img/pzds/1a.png';
    document.getElementById('emoji2').src = '/assets/img/pzds/2.png';
    document.getElementById('emoji3').src = '/assets/img/pzds/3.png';
    document.getElementById('emoji4').src = '/assets/img/pzds/4.png';
    document.getElementById('emoji5').src = '/assets/img/pzds/5.png';
}

function emoji2() {
    document.getElementById('emoji1').src = '/assets/img/pzds/1.png';
    document.getElementById('emoji2').src = '/assets/img/pzds/2a.png';
    document.getElementById('emoji3').src = '/assets/img/pzds/3.png';
    document.getElementById('emoji4').src = '/assets/img/pzds/4.png';
    document.getElementById('emoji5').src = '/assets/img/pzds/5.png';
}

function emoji3() {
    document.getElementById('emoji1').src = '/assets/img/pzds/1.png';
    document.getElementById('emoji2').src = '/assets/img/pzds/2.png';
    document.getElementById('emoji3').src = '/assets/img/pzds/3a.png';
    document.getElementById('emoji4').src = '/assets/img/pzds/4.png';
    document.getElementById('emoji5').src = '/assets/img/pzds/5.png';
}

function emoji4() {
    document.getElementById('emoji1').src = '/assets/img/pzds/1.png';
    document.getElementById('emoji2').src = '/assets/img/pzds/2.png';
    document.getElementById('emoji3').src = '/assets/img/pzds/3.png';
    document.getElementById('emoji4').src = '/assets/img/pzds/4a.png';
    document.getElementById('emoji5').src = '/assets/img/pzds/5.png';
}

function emoji5() {
    document.getElementById('emoji1').src = '/assets/img/pzds/1.png';
    document.getElementById('emoji2').src = '/assets/img/pzds/2.png';
    document.getElementById('emoji3').src = '/assets/img/pzds/3.png';
    document.getElementById('emoji4').src = '/assets/img/pzds/4.png';
    document.getElementById('emoji5').src = '/assets/img/pzds/5a.png';
}