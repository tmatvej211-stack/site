var URL_SERVER = 'https://' + document.domain;
var logged_in = false;
var mines_am = 2;
const coefList = document.getElementById('coefList');
const mineRange = document.getElementById('mineRange');
let currentRollData = null;
var USER_ID;
const tileSound = new Audio('assets/sounds/tile_open.mp3');

// функция для воспроизведения
function playTileSound() {
  if (localStorage.getItem('sounds') === 'true') {
    tileSound.currentTime = 0; // сбросить воспроизведение с начала
    tileSound.volume = 0.5;    // 50% громкости
    tileSound.play().catch(err => console.error("Ошибка воспроизведения:", err));
  }
}

$(document).ready(function() {
    $('#promo').on('input', function() {
        var value = $(this).val().trim(); // получаем текст и убираем пробелы
        if (value === '') {
            $('.g-recaptcha').hide(); // скрыть капчу
        } else {
            $('.g-recaptcha').show(); // показать капчу
        }
    });

    // Инициалально проверяем поле при загрузке страницы
    if ($('#promo').val().trim() === '') {
        $('.g-recaptcha').hide();
    }
});



(() => {
  const wheel = document.querySelector(".x50-wheel");
  const arrow = document.querySelector(".fa-caret-up");
  if (!wheel || !arrow) return console.error("Не найдено колесо или стрелка");

  const COLOR = {
    gray:   "#323537",
    yellow: "#DA9A00",
    red:    "#D83C33",
    green:  "#50B251",
  };

  const SEGMENT_COUNT = 55;
  const STEP_DEG = 360 / SEGMENT_COUNT; // ≈ 6.5454545°
  const FIRST_CENTER_DEG = 1.30;

  const POINTER_CENTER_ON_IMAGE_DEG = 266.5;
  let CALIBRATION_DEG = 3.8;

  const SEQ = [
    'gray','yellow','gray','yellow','gray','red','gray','red','gray','yellow','gray',
    'yellow','gray','yellow','gray','yellow','gray','yellow','gray','red','gray','red',
    'gray','yellow','gray','yellow','gray','yellow','gray','red','gray','red','gray',
    'yellow','gray','yellow','gray','yellow','gray','red','green','red','gray','yellow',
    'gray','yellow','gray','yellow','gray','red','gray','red','gray','yellow','gray'
  ];

  function getRotationDeg(el) {
    const t = getComputedStyle(el).transform;
    if (!t || t === 'none') return 0;
    const m = new DOMMatrixReadOnly(t);
    let deg = Math.atan2(m.b, m.a) * 180 / Math.PI;
    return deg < 0 ? deg + 360 : deg;
  }

  function circularIndex(x, n) {
    return ((x % n) + n) % n;
  }

  let lastRotation = null;

  function updateArrowColor() {
    const rot = getRotationDeg(wheel);

    // обновляем только если угол реально изменился (чтобы не лагало)
    if (rot === lastRotation) return;
    lastRotation = rot;

    let imgAngle = POINTER_CENTER_ON_IMAGE_DEG + CALIBRATION_DEG - rot;
    imgAngle = circularIndex(imgAngle, 360);

    let idx = Math.floor((imgAngle - FIRST_CENTER_DEG) / STEP_DEG);
    idx = circularIndex(idx, SEGMENT_COUNT);

    const colorKey = SEQ[idx];
    arrow.style.color = COLOR[colorKey] || COLOR.gray;
  }

  // плавное отслеживание вращения без лагов
  function loop() {
    updateArrowColor();
    requestAnimationFrame(loop);
  }
  requestAnimationFrame(loop);

  // экспорт в window для ручного обновления/калибровки
  window.updateArrowColor = updateArrowColor;
  window.setCalib = (deg) => { 
    CALIBRATION_DEG = deg;
    updateArrowColor();
  };
})();






function startOrSyncX50Wheel() {
    if (!currentRollData) return;

    const { rotate, duration, startTime } = currentRollData;
    const elapsed = Date.now() - startTime;

    const $wheel = $(".x50-wheel");
    if (elapsed >= duration) {
        $wheel.css('transition', 'none').css('transform', `rotate(${rotate}deg)`);
        return;
    }

    const progress = Math.max(0, Math.min(1, elapsed / duration));
    const currentRotate = rotate * progress;
    const remaining = Math.max(0, duration - elapsed);

    $wheel.css({
        'transition': 'none',
        'transform-origin': '50% 50%',
        'transform': `rotate(${currentRotate}deg)`
    });
	
	$("#status-x50").html('Вращение...');

    requestAnimationFrame(() => {
        requestAnimationFrame(() => {
            $wheel.css('transition', `transform ${remaining}ms cubic-bezier(.4,.1,0,1)`);
            $wheel.css('transform', `rotate(${rotate}deg)`);
        });
    });
}

document.addEventListener('DOMContentLoaded', function() {
    if (localStorage.getItem('sounds') === null) {
        localStorage.setItem('sounds', 'true');
    }
});

function open_page(e){
  $('body, html').scrollTop(0);
  $(".win-popup").hide();
  $(".main-content").hide();
  $("#" + e).show();
  if(e == "mines") {
	  var scrollContainer = document.querySelector('.scroll-container');
var activeCoef = document.querySelector('.coef.active');

if (activeCoef && scrollContainer) {
    activeCoef.scrollIntoView({
        behavior: 'smooth',
        inline: 'center', // или 'start', 'end' - в зависимости от нужного выравнивания
        block: 'nearest' // для горизонтального скролла обычно не важно
    });
}		
  }
    if (e === "x50") {
        startOrSyncX50Wheel();
    }    
}

function modal(id) {
    const modalEl = document.getElementById(id);
    if (!modalEl) return console.error(`Модалка с id="${id}" не найдена`);

    modalEl.style.display = 'flex';

    const closeEls = modalEl.querySelectorAll('.modal-close');
    closeEls.forEach(btn => {
        btn.addEventListener('click', () => closeModal(modalEl), { once: true });
    });

    modalEl.addEventListener('click', function handler(e) {
        if (e.target === modalEl) {
            closeModal(modalEl);
            modalEl.removeEventListener('click', handler);
        }
    });

    function escHandler(e) {
        if (e.key === 'Escape') {
            closeModal(modalEl);
            window.removeEventListener('keydown', escHandler);
        }
    }
    window.addEventListener('keydown', escHandler);
}

function closeModal(el) {
    el.style.display = 'none';
}


function closeModal(el) {
    el.style.display = 'none';
}

function openCheck(game, hash, salt, result){
   if(game == "") return notify('error', 'Для начала сыграйте игру!');
   modal('verify' + game);
   $("#hashCheck" + game).val(hash);
   $("#resultCheck" + game).val(result);
   $("#saltCheck" + game).val(salt);
}

var socket = null;

function generateCoefficients(mines) {
  coefList.innerHTML = '';

  const xb = 25 - Number(mines); // количество безопасных клеток
  if (xb <= 0) return;

  // стартовое значение exactly как на бэке
  let next_coeff = (25 / xb) * 0.96; // full precision (не округляем здесь)
  let steps = 0;

  while (steps < xb) {
    // 1) Визуальное отображение (2 знака), именно так, как видит пользователь
    const display = Number(next_coeff.toFixed(2));

    const coefDiv = document.createElement('div');
    coefDiv.className = 'coef';
    coefDiv.id = (steps + 1).toString(); // добавляем id начиная с 1
    
    // Создаем span для шага
    const stepSpan = document.createElement('span');
    stepSpan.className = 'step-mine-coeff';
    stepSpan.textContent = `шаг ${coefDiv.id}`;
    
    // Добавляем коэффициент и span в div
    coefDiv.textContent = `x${display.toFixed(2)} `;
    coefDiv.appendChild(stepSpan);
    
    coefList.appendChild(coefDiv);

    // 2) Подготовка следующего коэффициента — строго по бэку:
    // nx = xb - game.steps  (game.steps = steps в нашей симуляции)
    const nx = xb - steps;
    // формула: ((25 - game.steps) / nx) * game.next_coeff
    // где game.next_coeff — текущее (непосредственно предыдущее) значение next_coeff (full precision)
    const calc = ((25 - steps) / nx) * next_coeff;

    // сервер делает parseFloat(...).toFixed(2) — то есть сохраняет ОКРУГЛЁННОЕ до 2 знаков
    // мы имитируем это: округляем и используем это округлённое значение для следующей итерации
    next_coeff = Number(calc.toFixed(2));

    // 3) далее шаг увеличивается (как на сервере: steps++ после обновления next_coeff)
    steps++;
  }
}


mineRange.addEventListener('input', () => {
    generateCoefficients(mineRange.value);
});

generateCoefficients(mineRange.value);

$(function() {
	
socket = io(URL_SERVER, {
    path: '/socket.io/'
});
	
    socket.on('connect', function(msg){	  	
	   socket.emit('hash', auth_token);
	});
	
	socket.on('message', function(message){
        emit(message);
    });	
	
	socket.on('disconnect', function(message){
        notify('error', 'Ошибка подключения!');
    });		
	
	socket.on('counter', function(data){
	    $(".online").html(data.data);
    });		
});

    const range = document.getElementById('mineRange');
    const tooltip = document.getElementById('tooltip');

    function updateTooltipPosition() {
      const percent = (range.value - range.min) / (range.max - range.min);
      const thumbWidth = 20; // as defined in CSS
      const trackWidth = range.offsetWidth;
      const left = percent * (trackWidth - thumbWidth) + thumbWidth / 2;

      tooltip.style.left = `${left}px`;
      tooltip.textContent = range.value;
	  mines_am = range.value;
    }

    range.addEventListener('input', () => {
      updateTooltipPosition();
      tooltip.style.opacity = '1';
    });

    range.addEventListener('mouseup', () => {
      tooltip.style.opacity = '0';
    });

    range.addEventListener('touchend', () => {
      tooltip.style.opacity = '0';
    });

    // Init position
    updateTooltipPosition();

function send(msg) {
	if (socket) {
		socket.emit('mes', msg);
	}
}

document.addEventListener('DOMContentLoaded', () => {
  const carousel = document.getElementById('carousel');
  const carouselInner = document.getElementById('carouselInner');
  const indicatorsContainer = document.getElementById('carouselIndicators');

  const slides = carouselInner.querySelectorAll('.carousel-slide');
  let index = 0;
  let autoSlide;
  let startX = 0;
  let currentTranslate = 0;
  let prevTranslate = 0;
  let dragging = false;

  // Создаём индикаторы
  slides.forEach((_, i) => {
    const dot = document.createElement('div');
    dot.classList.add('indicator');
    dot.addEventListener('click', () => showSlide(i));
    indicatorsContainer.appendChild(dot);
  });
  const indicators = indicatorsContainer.querySelectorAll('.indicator');

  function updateIndicators() {
    indicators.forEach((dot, i) => {
      dot.classList.toggle('active', i === index);
    });
  }

  function showSlide(i, animate = true) {
    index = (i + slides.length) % slides.length;
    carouselInner.style.transition = animate ? 'transform 0.4s ease' : 'none';
    currentTranslate = -index * carousel.offsetWidth;
    prevTranslate = currentTranslate;
    carouselInner.style.transform = `translateX(${currentTranslate}px)`;
    updateIndicators();
  }

  function nextSlide() { showSlide(index + 1); }
  function prevSlide() { showSlide(index - 1); }

  // Автопрокрутка
  function startAutoSlide() {
    autoSlide = setInterval(nextSlide, 5000);
  }
  function stopAutoSlide() {
    clearInterval(autoSlide);
  }

  carousel.addEventListener('mouseenter', stopAutoSlide);
  carousel.addEventListener('mouseleave', startAutoSlide);

  // ==== Полноценный свайп ====
  carousel.addEventListener('touchstart', touchStart);
  carousel.addEventListener('touchmove', touchMove);
  carousel.addEventListener('touchend', touchEnd);

  function touchStart(e) {
    startX = e.touches[0].clientX;
    dragging = true;
    stopAutoSlide();
    carouselInner.style.transition = 'none';
  }

  function touchMove(e) {
    if (!dragging) return;
    const currentX = e.touches[0].clientX;
    const diff = currentX - startX;
    currentTranslate = prevTranslate + diff;
    carouselInner.style.transform = `translateX(${currentTranslate}px)`;
  }

  function touchEnd(e) {
    dragging = false;
    const movedBy = currentTranslate - prevTranslate;

    if (movedBy < -50) {
      nextSlide();
    } else if (movedBy > 50) {
      prevSlide();
    } else {
      showSlide(index); // вернуться на место
    }
    startAutoSlide();
  }

  // Инициализация
  showSlide(0);
  startAutoSlide();

  // Подгон ширины при ресайзе
  window.addEventListener('resize', () => {
    showSlide(index, false);
  });
});

function withdrawRefBalance(){
   send({"type": "withdrawRefBalance"})
}

function copyReferralLink() {
  // Находим элемент с классом referral-link
  const referralLinkDiv = document.querySelector('div.referral-link');
  
  if (referralLinkDiv) {
    // Получаем текст из div
    const textToCopy = referralLinkDiv.textContent || referralLinkDiv.innerText;
    
    // Используем Clipboard API для копирования
    navigator.clipboard.writeText(textToCopy.trim())
      .then(() => {
        notify('success', 'Реферальная ссылка успешно скопирована!');
      })
      .catch(err => {
        // Альтернативный метод для старых браузеров
        const textarea = document.createElement('textarea');
        textarea.value = textToCopy.trim();
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        notify('success', 'Реферальная ссылка успешно скопирована!');
      });
  } else {
    console.error('Элемент с классом referral-link не найден');
  }
}

const content = document.getElementById('collapsible-content');
const arrow = document.getElementById('toggle-arrow');

// Отменяем выделение при клике
arrow.addEventListener('mousedown', e => e.preventDefault());

arrow.addEventListener('click', () => {
  content.classList.toggle('expanded');
  arrow.classList.toggle('rotated');
});


$(document).on('click', '.outcome-option', function() {
    $(".outcome-option").removeClass("active");
	$(this).addClass('active');
});

function createMines() {
    send({"type": "createMines", "amount": $("#betMines").val(), "mines": mines_am})
}

function sendDice_animation(e){
	  $("#dicer").html('');
      lottie.loadAnimation({
        container: document.getElementById('dicer'),
        renderer: 'svg',
        loop: false,
        autoplay: true,
        path: 'assets/animations/dice'+e+'.json'
      });	
}

let currentGameMode = '';
let currentNumber = null;

// Установка режима игры
function setGameMode(mode, number = null, el) {
	$(".outcome-option").removeClass("active");
	$(el).addClass('active');
    currentGameMode = mode;
    currentNumber = number;
}

function betCube() {
    send({"type": "betCube", "bet": $("#betDice").val(), "mode": currentGameMode, "number": currentNumber})
}

function diceCheck(e) {
    send({"type": "diceCheck", "id": e})
}

function minesCheck(e) {
    send({"type": "minesCheck", "id": e})
}

function x50Check(e) {
    send({"type": "x50Check", "id": e})
}

function notify(res, msg){
 if(res == "error") var title = "Ошибка";
 if(res == "success") var title = "Успешно";
 if($(".sn-notify").length > 0){
    $(".sn-notify").remove();
 }
 new Notify({
    status: res,
    title: title,
    text: msg,
    effect: 'fade',
    speed: 300,
    customClass: null,
    customIcon: null,
    showIcon: true,
    showCloseButton: true,
    autoclose: true,
    autotimeout: 2000,
    gap: 20,
    distance: 20,
    type: 'outline',
    position: 'x-center'
  })	
}

function getCookie(name) {
    const nameEncoded = encodeURIComponent(name).replace(/[\-\.\+\*]/g, "\\$&");    
    const regex = new RegExp(`(?:(?:^|.*;\\s*)${nameEncoded}\\s*\\=\\s*([^;]*).*$)|^.*$`);   
    const value = document.cookie.replace(regex, "$1");
    return value ? decodeURIComponent(value) : null;
}

// Текущие значения
let currentCoeff = 0;
let currentBet = 0;
let balance = 10000; // Примерный баланс, можно получить из системы

// Обработка кнопок быстрого увеличения ставки
document.querySelectorAll('.quick-amount').forEach(button => {
  button.addEventListener('click', () => {
	if(button.getAttribute('data-game') == "cube") var betInput = document.querySelector('#betDice');
	if(button.getAttribute('data-game') == "mines") var betInput = document.querySelector('#betMines');
	if(button.getAttribute('data-game') == "x50") var betInput = document.querySelector('#betX50');
    let currentValue = parseInt(betInput.value) || 0;
    const balance = parseInt(document.getElementById('balance').textContent) || 0;

    const action = button.getAttribute('data-add');
    
    switch (action) {
      case 'clear':
        currentValue = '';
        break;
      case 'plus100':
        currentValue += parseInt(100);
        break;	  
      case 'plus1k':
        currentValue += parseInt(1000);
        break;
      case 'half':
        currentValue = parseInt(currentValue / 2);
        break;		
      case 'double':
        currentValue = parseInt(currentValue * 2);
        break;
      case 'all':
        currentValue = balance;
        break;
    }

    // Ограничим значение балансом и не даём отрицательных значений
    currentValue = Math.min(currentValue, balance);
    currentValue = Math.max(0, currentValue);

    // Обновим input
    betInput.value = currentValue;

    // Обновим возможный выигрыш
    if (typeof calculateWin === 'function') {
      calculateWin();
    }

    // Триггерим событие input
    betInput.dispatchEvent(new Event('input', { bubbles: true }));
  });
});

function updateBalance(e){
   $(".balance").html(e);
   $("#balance").html(e);
}

$(document).ready(function() {
  $('#amountWithdraw').on('keyup', function() {
    let value = $(this).val();

    // Пример: оставить только цифры и точку (если это денежное значение)
    value = value.replace(/[^\d.]/g, '');

    if (value.indexOf('.') !== -1) {
      let parts = value.split('.');
      parts[1] = parts[1].substring(0, 2);
      value = parts[0] + '.' + parts[1];
    }

    var valueinusdt = (value / 100) * 1;
	$("#toGetWithdraw").val(parseFloat(valueinusdt).toFixed(2));
  });
});

function withdraw(){
   send({"type": "withdraw", "amount": $("#amountWithdraw").val()})
}

function historyTransactions(){
  send({"type": "historyTransactions"})
}

$(document).on('click', '.transaction-cancel', function() {
    // Получаем ID выплаты из data-атрибута
    const withdrawalId = $(this).data('id');
    
    // Добавляем класс "loading" для индикации загрузки
    $(this).html('<i class="fas fa-spinner fa-spin"></i>');
    $(this).prop('disabled', true);
    
    send({"type": "cancelWithdrawal", "id": withdrawalId})
});

function promo(){
  send({"type": "activatePromo", "promo": $("#promo").val(), "captcha": $("#g-recaptcha-response").val()})
}

function deposit() {
    const formData = new FormData();
    formData.append('amount', $("#depAmount").val());
    formData.append('user_id', USER_ID);

    fetch('api/redirect_deposit_kb.php', { // замените на путь к вашему PHP-файлу
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success === 'success') {
            // Перенаправляем на ссылку оплаты
            // Открыть ссылку оплаты в новой вкладке
window.open(data.pay_url, '_blank');
        } else {
            notify('error', data.mess);
        }
    })
    .catch(error => {
        console.error('Ошибка:', error);
		notify('error', 'Произошла ошибка при отправке запроса.');
    });
}

$('.cell').on('click', function() {
  var tile = $(this).attr('id');
  send({"type": "playMines", "tile": tile})
});

function shuffle_arr(e) {
    let t, a = e.length;
    for (; 0 != a;) t = Math.floor(Math.random() * a), a--, [e[a], e[t]] = [e[t], e[a]];
    return e
}

function cashoutMines() {
    send({
        type: "takeMines"
    })
}

function joinx50(e){
   send({
	   type: "joinx50",
	   amount: $("#betX50").val(),
	   where: e
   });
}

// Обновленная функция добавления элемента истории
function addHistX50(id, coeff) {
    const historyContainer = $(".x50-history");
    const historyLines = historyContainer.find(".line-x50");
    
    // Удаляем первую строку, если достигли лимита (20 строк)
    if (historyLines.length >= 20) {
        historyLines.first().remove();
    }
    
    // Добавляем новую строку (prepend - чтобы новые были сверху)
    historyContainer.prepend(
        `<div class="line-x50 x${coeff}-color" onclick="x50Check(${id})" idroll="${id}">
        </div>`
    );
}

let wheelStartTime = null; // время старта от сервера
let wheelEndRotate = null;
let wheelDuration = 10000; // ms

function isVisible(el) {
    if (!el || !el.length) return false;
    const dom = el[0] || el;
    const rects = dom.getClientRects();
    if (!rects || rects.length === 0) return false;
    const cs = window.getComputedStyle(dom);
    return cs.display !== 'none' && cs.visibility !== 'hidden' && cs.opacity !== '0';
}

function applyAnimationToWheel() {
    const $wheel = $('.x50-wheel');
    if (!$wheel.length || wheelStartTime == null || wheelEndRotate == null) return;

    const now = Date.now();
    const elapsed = now - wheelStartTime;

    if (elapsed >= wheelDuration) {
        $wheel.css('transition', 'none').css('transform', `rotate(${wheelEndRotate}deg)`);
        return;
    }

    const progress = Math.max(0, Math.min(1, elapsed / wheelDuration));
    const currentRotate = wheelEndRotate * progress;
    const remaining = Math.max(0, wheelDuration - elapsed);

    $wheel.css({
        'transition': 'none',
        'transform-origin': '50% 50%',
        'transform': `rotate(${currentRotate}deg)`
    });

    requestAnimationFrame(() => {
        requestAnimationFrame(() => {

            // Случайное небольшое смещение в конце, чтобы интрига была
            const sectors = 12; // допустим, 12 секторов
            const sectorAngle = 360 / sectors;
            const randomOffset = (Math.random() - 0.5) * sectorAngle; // может перекатиться на соседний

            $wheel.css('transition', `transform ${remaining}ms cubic-bezier(0.25, 0.1, 0.25, 1)`);
            $wheel.css('transform', `rotate(${wheelEndRotate + randomOffset}deg)`);
        });
    });
}


function waitForVisibleAndApply() {
    const $x50 = $('#x50');
    if (isVisible($x50) && isVisible($('.x50-wheel'))) {
        applyAnimationToWheel();
        cleanupWatchers();
        return true;
    }
    return false;
}

function cleanupWatchers() {
    if (window._x50_waitInterval) {
        clearInterval(window._x50_waitInterval);
        window._x50_waitInterval = null;
    }
    if (window._x50_mutObserver) {
        try { window._x50_mutObserver.disconnect(); } catch(e){}
        window._x50_mutObserver = null;
    }
    if (window._x50_visibilityHandler) {
        document.removeEventListener('visibilitychange', window._x50_visibilityHandler);
        window._x50_visibilityHandler = null;
    }
}

function emit(message)
{
	var m = message;
	if(m.type == "welcome"){
	   startOrSyncX50Wheel();
	   $("#preloader").fadeOut();
	   logged_in = true;
	   $("#logged_out_user_block").hide();
	   USER_ID = m.userId;
	   $("#logged_in_user_block").css("display", "flex");
	   $(".balance").html(parseInt(m.balance));
	   $(".profile-icon").html("<img src='"+m.avatar+"?v="+m.time+"' style='border-radius:999px' width='36'/>");
	   $("#userId").html(USER_ID);
	   $(".referral-link").html('https://t.me/WelpGameBot?start='+m.tg_id+'');
	   $("#refTotal").html(m.refs);
	   $("#refEarned").html(m.ref_earned);
	   $("#refAvailable").html(m.ref_available);
	}	
	if(m.type == "refWithdrawSuccess"){
	   updateBalance(m.new_balance);
	   $("#refAvailable").html(0);
	   notify('success', m.message);
	}
	if(m.type == "checkX50"){
	   modal('verifyX50');
	   $("#hashCheckX50").val(m.hash);
	   $("#resultCheckX50").val(m.coeff);
	   $("#saltCheckX50").val(m.salt);
	}
	if(m.type == "setTimesX50"){
	   $("#status-x50").html(m.text);
	}
if (m.type == "getHistory50") {
    // Проверяем, что history существует и является массивом
    if (Array.isArray(m.history)) {
        // Очищаем текущую историю на клиенте
        $(".x50-history").empty();
        
        // Добавляем все элементы истории (в правильном порядке)
        m.history.forEach(item => {
			addHistX50(item.game_id, item.coeff);
        });
    }
}
if (m.type == "addBetX50") {
    var bet_info = m.bet;

    var $list = $(".x" + bet_info.where + " .bet-list");

    // Скрываем "нет ставок"
    $(".x" + bet_info.where + " .no-bets").hide();

    // Добавляем элемент в конец (временная вставка)
    $list.append(
        '<div class="bet-item">' +
        '<span class="nickname-x50">' + bet_info.name + '</span> ' +
        '<span class="amount">' + bet_info.amount + '</span>' +
        '</div>'
    );

    // Сортируем по суммам (по убыванию)
    var $items = $list.children(".bet-item");
    $items.sort(function(a, b) {
        var amountA = parseFloat($(a).find(".amount").text()) || 0;
        var amountB = parseFloat($(b).find(".amount").text()) || 0;
        return amountB - amountA; // больше → выше
    });

    // Переставляем отсортированные элементы в DOM
    $list.html($items);

    // Обновляем общий total
    var amounts_total = $("#bets_total_x" + bet_info.where).text();
    var number_amounts_total = Number(amounts_total);
    number_amounts_total += bet_info.amount;
    $("#bets_total_x" + bet_info.where).html(parseInt(number_amounts_total));
}

	if(m.type == "resetX50"){
	   $(".x2 .bet-list").html('');
	   $(".x3 .bet-list").html('');
	   $(".x5 .bet-list").html('');
	   $(".x50 .bet-list").html('');
	   $("#bets_total_x2").html(0);
	   $("#bets_total_x3").html(0);
	   $("#bets_total_x5").html(0);
	   $("#bets_total_x50").html(0);
	   $(".no-bets").show();
	}
if (m.type == "rollX50") {
	
	currentRollData = m;
    startOrSyncX50Wheel();
}
if ("playMines" === m.type) {
    if (1 === m.win) {
		playTileSound();
const scrollContainer = document.querySelector('.scroll-container');
const activeCoef = document.querySelector('.coef.active');

        // Обработка выигрышного случая
        $('button[onclick="cashoutMines()"]').html("Забрать " + parseInt(m.currentAmount));
        $('.cell[id="' + m.tile + '"]')
            .removeClass("w-tile")
			.addClass("gem");
        
        // Обновление счетчиков
        var remainingCells = parseInt($("#goodmines").text());
        remainingCells -= 1;
        $("#goodmines").html(remainingCells);
        $("#curcoeffmines").html(parseFloat(m.current_coeff).toFixed(2) + "x");
        $("#nextcoeffmines").html(parseFloat(m.next_coeff).toFixed(2) + "x");
		var step = m.steps;
		$(".coef").removeClass("active");
		$('.coef[id="'+step+'"]').addClass("active");
if (activeCoef && scrollContainer) {
    activeCoef.scrollIntoView({
        behavior: 'smooth',
        inline: 'center', // или 'start', 'end' - в зависимости от нужного выравнивания
        block: 'nearest' // для горизонтального скролла обычно не важно
    });
}				
		$("#current-profit-mines").val(parseInt(m.currentAmount-m.bet));
		$("#current-coeff-mines").html(parseFloat(m.current_coeff).toFixed(2));
		const remainingSafeCells = 25 - m.mines - m.steps;
		$("#gems-current").val(remainingSafeCells);
        
        // Проверка последней клетки
        if (1 === m.last_kletka) {
            $("#crmines").prop("disabled", true);
            setTimeout(function() {
                cashoutMines();
            }, 900);
        }
    } else {
var scrollContainer = document.querySelector('.scroll-container');
if (scrollContainer) {
    scrollContainer.scrollLeft = 0; // Прокрутка в начало
}		
	$(".block-mines-info").hide();
	$(".coef").removeClass("active");
    $('.cell[id="' + m.tile + '"]')
        .removeClass("w-tile")
        .addClass("bomb");		
        // Обработка проигрышного случая
        $("#crmines").prop("disabled", true);
        $("#automines").prop("disabled", true);
		$("#mineRange").prop('disabled', false);
        
// Берём массив бомб из ответа сервера
const loseTiles = m.resultMines.split("|");

// Помечаем каждую как бомбу
loseTiles.forEach(tileId => {
    $('.cell[id="' + tileId + '"]')
        .removeClass("w-tile")
        .addClass("bomb");
});
	$("#minesHash").html(m.hash);
	$("#minesResultCheck").html(m.resultMines);
	$("#minesSalt").html(m.salt);
	$("#mines_result_span").show();
	$("#mines_salt_span").show();
	$("#mines_check_span").show();

// Все оставшиеся клетки (кроме бомб) делаем "gem"
$('.cell:not(.bomb)').each(function() {
    $(this)
        .removeClass("w-tile")
        .addClass("gem");
});

$("#verifyBtnMines").prop('disabled', false);
$("#verifyBtnMines").html('<i class="fa-solid fa-shield-halved"></i>');
$("#verifyBtnMines").attr("onclick", "openCheck('Mines', '"+m.hash+"', '"+m.salt+"', '"+m.resultMines+"')");
        
        // Сброс игры через 1.2 секунды
            // Сброс кнопки
            $('button[onclick="cashoutMines()"]')
                .html("Сделать ставку")
                .attr("onclick", "createMines()")
				.prop('disabled', false);
    }
}	
if ("cashoutMines" === m.type) {
    // Обновляем баланс
var scrollContainer = document.querySelector('.scroll-container');
if (scrollContainer) {
    scrollContainer.scrollLeft = 0; // Прокрутка в начало
}	
if (localStorage.getItem('sounds') === 'true') {
    var Sound1 = new Audio('assets/sounds/win.mp3?v=4');
    Sound1.currentTime = 0; // сбросить воспроизведение с начала
	Sound1.volume = 0.5;    // 30% громкости
    Sound1.play().catch(err => console.error("Ошибка воспроизведения:", err));
}		
// Берём массив бомб из ответа сервера
const loseTiles = m.resultMines.split("|");

// Помечаем каждую как бомбу
loseTiles.forEach(tileId => {
    $('.cell[id="' + tileId + '"]')
        .removeClass("w-tile")
        .addClass("bomb");
});
$('.cell:not(.bomb)').each(function() {
    $(this)
        .removeClass("w-tile")
        .addClass("gem");
});
    notify('success', "Вы выиграли <b>"+parseInt(m.win_sum)+"</b> монеток!");
	$(".coef").removeClass("active");	
    updateBalance(m.newbalance);
    $(".win-popup").show();
	$("#mineRange").prop('disabled', false);
	$(".block-mines-info").hide();
	$("#popup-win-amount").html(parseInt(m.win_sum));
	$("#popup-multiplier").html(parseFloat(m.coeff).toFixed(2));
    // Сбрасываем коэффициенты
    $("#curcoeffmines").html("1.00x");
    $("#nextcoeffmines").html("1.00x");
	$("#minesCheck").attr("onclick", "minesCheck("+m.game_id+")");
	
	$("#verifyBtnMines").prop('disabled', false);
    $("#verifyBtnMines").html('<i class="fa-solid fa-shield-halved"></i>');
    $("#verifyBtnMines").attr("onclick", "openCheck('Mines', '"+m.hash+"', '"+m.salt+"', '"+m.resultMines+"')");
    
    // Обновляем кнопку
    $('button[onclick="cashoutMines()"]')
        .html("Сделать ставку")
        .attr("onclick", "createMines()");
    
    // Сбрасываем счетчики
    $("#goodmines").html("...");
    $("#badmines").html("...");
    
    // Обновляем хеш и состояние кнопок
    //$("#hashMines").html(m.hash);
    $("#crmines").prop("disabled", false);
    $("#automines").prop("disabled", true);
}
if (m.type === "activeMines") {
	$("#verifyBtnMines").prop('disabled', true);
    $("#verifyBtnMines").html('<i class="fa-solid fa-spinner"></i>');	
	$("#mineRange").prop('disabled', true);
    // Установка значений
    $("#betMines").val(parseInt(m.bet));
	$(".block-mines-info").show();
		generateCoefficients(m.mines);
		var step = m.steps;
		$(".coef").removeClass("active");
		$('.coef[id="'+step+'"]').addClass("active");	
	$("#current-coeff-mines").html(parseFloat(m.current_coeff).toFixed(2));
	$("#bombs-current").val(m.mines);
	const remainingSafeCells = 25 - m.mines - m.steps;
	$("#gems-current").val(remainingSafeCells);
	$("#current-profit-mines").val(parseInt(m.currentAmount-m.bet));
    $("#curcoeffmines").html(parseFloat(m.current_coeff).toFixed(2) + "x");
    $("#nextcoeffmines").html(parseFloat(m.next_coeff).toFixed(2) + "x");
    $("#crmines")
        .attr("onclick", "cashoutMines()")
        .html("Забрать " + parseInt(m.currentAmount));

    // Отображение активных плиток
    const tiles = m.tiles.split("|");
    for (let i = 0; i < tiles.length; i++) {
        const tileId = tiles[i];
        const tileElement = $('.w-tile[id="' + tileId + '"]');
        tileElement
            .removeClass("w-tile")
            .addClass("gem");
    }
}
	
	if(m.type == "updateBalance"){
	   updateBalance(parseInt(m.balance));
	}
    if(m.type == "transactionsHistory"){
	   $(".transactions-list").html(m.html);
	}
	if(m.type == "cubeResult"){
	   $('.place-bet-button').prop('disabled', true);
	   $("#verifyBtnDice").html('<i class="fa-solid fa-spinner"></i>');
	   $("#diceCheck").attr("onclick", "diceCheck("+m.gameId+")");
	   sendDice_animation(m.cubeResult);
	   updateBalance(parseInt(m.oldBalance));
	   setTimeout(function(){ 
		   $("#verifyBtnDice").html('<i class="fa-solid fa-shield-halved"></i>');
		   $("#verifyBtnDice").attr("onclick", "openCheck('Cube', '"+m.hash+"', '"+m.salt+"', '"+m.cubeResult+"')");
		   if(m.win){
			   notify('success', "Вы выиграли <b>"+parseInt(m.winAmount+m.userBet)+"</b> монеток!");
               if (localStorage.getItem('sounds') === 'true') {
                  var Sound1 = new Audio('assets/sounds/win.mp3?v=5');
                  Sound1.currentTime = 0; // сбросить воспроизведение с начала
	              Sound1.volume = 0.5;    // 30% громкости
                  Sound1.play().catch(err => console.error("Ошибка воспроизведения:", err));
               }					   
		   }
		   $('.place-bet-button').prop('disabled', false);
		   updateBalance(parseInt(m.newBalance));
	   }, 3100);
	}		
	if(m.type == "newMines"){
	$("#verifyBtnMines").prop('disabled', true);
    $("#verifyBtnMines").html('<i class="fa-solid fa-spinner"></i>');
	$("#mineRange").prop('disabled', true);
var scrollContainer = document.querySelector('.scroll-container');
if (scrollContainer) {
    scrollContainer.scrollLeft = 0; // Прокрутка в начало
}		
			$("#minesCheck").attr("onclick", "minesCheck("+m.game_id+")");
            $("#crmines").prop("disabled", false);
            //$("#hashMines").html(m.hashMines);
            $("#goodmines").html("...");
            $("#badmines").html("...");
            
            // Сброс всех клеток
            $(".cell")
                .removeClass("bomb")
                .removeClass("gem")
                .addClass("w-tile");
            
            // Сброс коэффициентов
            $("#curcoeffmines").html("1.00x");
            $("#nextcoeffmines").html("1.00x");
            		
	   $(".block-mines-info").show();
	   $("#current-coeff-mines").html("1.00");
	   $("#current-profit-mines").val("0");
	   $("#bombs-current").val(m.mines);
	   var free = 25 - m.mines;
	   $("#gems-current").val(free);
	   $(".win-popup").hide();
	   $('button[onclick="createMines()"]').html('Забрать '+parseInt(m.bet)+'').attr("onclick", "cashoutMines()");
	   updateBalance(m.newbalance);
	}
	if(m.type == "alert"){
	   notify(m.type_alert, m.alert_message);
	   if(m.sound) {
		  if (localStorage.getItem('sounds') === 'true') {
             const Sound = new Audio('assets/sounds/' + m.sound + '?v=1');
             Sound.currentTime = 0; // сбросить воспроизведение с начала
		     Sound.volume = 0.5;    // 30% громкости
             Sound.play().catch(err => console.error("Ошибка воспроизведения:", err));
		  }		  
	   }
	   if(m.captcha) {
	      grecaptcha.reset();
	   }
	}
}