(function () {
  var form = document.querySelector('form[data-validate][data-dates]');
  if (!form) return;
  var checkIn = form.querySelector('#i');
  var checkOut = form.querySelector('#o');
  var status = document.getElementById('dateLiveStatus');
  var submit = document.getElementById('reserveSubmit');
  var ranges = [];
  try { var bookedData = JSON.parse(form.dataset.booked || '[]'); ranges = Array.isArray(bookedData) ? bookedData : (bookedData.ranges || []); } catch (_) { ranges = []; }

  function parse(v) { var p = v.split('-'); return new Date(Date.UTC(+p[0], +p[1]-1, +p[2])); }
  function fmt(d) { return d.getUTCFullYear() + '-' + String(d.getUTCMonth()+1).padStart(2,'0') + '-' + String(d.getUTCDate()).padStart(2,'0'); }
  function add(d, n) { var x = new Date(d); x.setUTCDate(x.getUTCDate()+n); return x; }
  function today(){ var d=new Date(); return new Date(Date.UTC(d.getFullYear(),d.getMonth(),d.getDate())); }

  function occupied(v){ var d=parse(v); return ranges.some(function(r){ return d>=parse(r.from) && d<=parse(r.to); }); }
  function inPast(v){ return parse(v) < today(); }
  function checkOutDisabled(v){
    if (!checkIn.value) return parse(v)<=today();
    var ci=parse(checkIn.value), d=parse(v);
    if (d<=ci || inPast(v)) return true;
    var next=null;
    ranges.forEach(function(r){ var f=parse(r.from); if(f>ci && (!next || f<next)) next=f; });
    return next ? d>=next : false;
  }

  function refreshRange(data){ ranges=(data&&data.ranges)||[]; updatePopupInputs(); checkAvailability(); }
  function fetchRanges(){
    fetch(form.dataset.bookedUrl,{headers:{'Accept':'application/json'}}).then(function(r){return r.json();}).then(refreshRange).catch(function(){/* Keep last known data */});
  }

  var popup;
  function closePopup(){ if(popup){ popup.remove(); popup=null; } }
  function calendar(input){
    closePopup();
    var isOut=input===checkOut;
    var monthStart=parse(input.value||fmt(today())); monthStart=new Date(Date.UTC(monthStart.getUTCFullYear(),monthStart.getUTCMonth(),1));
    popup=document.createElement('div'); popup.className='booking-calendar'; popup.setAttribute('role','dialog'); popup.setAttribute('aria-label', isOut?'Choose check-out date':'Choose check-in date');
    form.appendChild(popup);

    function render(){
      popup.innerHTML='';
      var head=document.createElement('div'); head.className='cal-head';
      var prev=document.createElement('button'); prev.type='button'; prev.className='cal-nav'; prev.textContent='‹'; prev.setAttribute('aria-label','Previous month');
      var title=document.createElement('strong'); title.textContent=monthStart.toLocaleDateString('en-PH',{month:'long',year:'numeric',timeZone:'UTC'});
      var next=document.createElement('button'); next.type='button'; next.className='cal-nav'; next.textContent='›'; next.setAttribute('aria-label','Next month');
      prev.onclick=function(){monthStart=new Date(Date.UTC(monthStart.getUTCFullYear(),monthStart.getUTCMonth()-1,1));render();};
      next.onclick=function(){monthStart=new Date(Date.UTC(monthStart.getUTCFullYear(),monthStart.getUTCMonth()+1,1));render();};
      head.append(prev,title,next); popup.appendChild(head);
      var grid=document.createElement('div'); grid.className='cal-grid';
      ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'].forEach(function(t){var e=document.createElement('span');e.className='cal-week';e.textContent=t;grid.appendChild(e);});
      var first=monthStart.getUTCDay(); for(var i=0;i<first;i++) grid.appendChild(document.createElement('span'));
      var days=new Date(Date.UTC(monthStart.getUTCFullYear(),monthStart.getUTCMonth()+1,0)).getUTCDate();
      for(var day=1;day<=days;day++){
        var d=new Date(Date.UTC(monthStart.getUTCFullYear(),monthStart.getUTCMonth(),day)); var value=fmt(d);
        var unavailable=isOut?checkOutDisabled(value):(inPast(value)||occupied(value));
        var selected=input.value===value; var b=document.createElement('button'); b.type='button'; b.className='cal-day'+(unavailable?' unavailable':'')+(selected?' selected':''); b.textContent=day; b.setAttribute('aria-label',d.toLocaleDateString('en-PH',{day:'numeric',month:'long',year:'numeric'})+(unavailable?', unavailable':''));
        b.disabled=unavailable;
        if(!unavailable) b.onclick=function(v){return function(){input.value=v; input.dispatchEvent(new Event('change',{bubbles:true})); closePopup();};}(value);
        grid.appendChild(b);
      }
      popup.appendChild(grid);
    }
    render();
  }

  [checkIn,checkOut].forEach(function(input){
    input.setAttribute('readonly','readonly'); input.classList.add('picker-input');
    input.addEventListener('click',function(){calendar(input);});
    input.addEventListener('keydown',function(e){if(e.key==='Enter'||e.key===' '){e.preventDefault();calendar(input);}});
  });
  document.addEventListener('click',function(e){if(popup&&!popup.contains(e.target)&&e.target!==checkIn&&e.target!==checkOut)closePopup();});

  function updatePopupInputs(){
    if (!checkIn.value || inPast(checkIn.value) || occupied(checkIn.value)) { /* keep server fallback */ }
  }

  var timer;
  function checkAvailability(){
    clearTimeout(timer);
    timer=setTimeout(function(){
      if(!form.dataset.availabilityUrl||!checkIn.value||!checkOut.value)return;
      fetch(form.dataset.availabilityUrl+'?check_in='+encodeURIComponent(checkIn.value)+'&check_out='+encodeURIComponent(checkOut.value),{headers:{'Accept':'application/json'}})
        .then(function(r){return r.json().then(function(j){return {ok:r.ok,json:j};});}).then(function(x){
          status.textContent=x.json.message||''; status.className='date-live-status '+(x.ok?'available':'unavailable'); if(submit) submit.disabled=!x.ok || !document.querySelector('[name=room_id]');
        }).catch(function(){status.textContent='Could not verify availability. Please try again.';status.className='date-live-status unavailable';});
    },250);
  }
  checkIn.addEventListener('change',checkAvailability); checkOut.addEventListener('change',checkAvailability);
  window.addEventListener('focus',fetchRanges);
  fetchRanges();
})();
