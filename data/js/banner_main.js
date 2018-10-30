/* Algoinde's banner generator */
/* (c) Eugene Kartashov, 2014 */

/* ------------------------------------------------------------------------------*/

/*
 * classList.js: Cross-browser full element.classList implementation.
 * 2014-01-31
 *
 * By Eli Grey, http://eligrey.com
 * Public Domain.
 * NO WARRANTY EXPRESSED OR IMPLIED. USE AT YOUR OWN RISK.
 */

/*global self, document, DOMException */

/*! @source http://purl.eligrey.com/github/classList.js/blob/master/classList.js*/
if("document"in self&&!("classList"in document.createElement("_"))) {(function(e){"use strict";if(!("Element"in e))return;var t="classList",n="prototype",r=e.Element[n],i=Object,s=String[n].trim||function(){return this.replace(/^\s+|\s+$/g,"")},o=Array[n].indexOf||function(e){var t=0,n=this.length;for(;t<n;t++){if(t in this&&this[t]===e){return t}}return-1},u=function(e,t){this.name=e;this.code=DOMException[e];this.message=t},a=function(e,t){if(t===""){throw new u("SYNTAX_ERR","An invalid or illegal string was specified")}if(/\s/.test(t)){throw new u("INVALID_CHARACTER_ERR","String contains an invalid character")}return o.call(e,t)},f=function(e){var t=s.call(e.getAttribute("class")||""),n=t?t.split(/\s+/):[],r=0,i=n.length;for(;r<i;r++){this.push(n[r])}this._updateClassName=function(){e.setAttribute("class",this.toString())}},l=f[n]=[],c=function(){return new f(this)};u[n]=Error[n];l.item=function(e){return this[e]||null};l.contains=function(e){e+="";return a(this,e)!==-1};l.add=function(){var e=arguments,t=0,n=e.length,r,i=false;do{r=e[t]+"";if(a(this,r)===-1){this.push(r);i=true}}while(++t<n);if(i){this._updateClassName()}};l.remove=function(){var e=arguments,t=0,n=e.length,r,i=false;do{r=e[t]+"";var s=a(this,r);if(s!==-1){this.splice(s,1);i=true}}while(++t<n);if(i){this._updateClassName()}};l.toggle=function(e,t){e+="";var n=this.contains(e),r=n?t!==true&&"remove":t!==false&&"add";if(r){this[r](e)}return!n};l.toString=function(){return this.join(" ")};if(i.defineProperty){var h={get:c,enumerable:true,configurable:true};try{i.defineProperty(r,t,h)}catch(p){if(p.number===-2146823252){h.enumerable=false;i.defineProperty(r,t,h)}}}else if(i[n].__defineGetter__){r.__defineGetter__(t,c)}})(self)}
/* ------------------------------------------------------------------------------*/

var _canvas, _layers, _preview, _url, _code, _craft, _jsonMode, _saved, _savedTemp;
_saved = [];
_savedTemp = [];
var base64dict = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789+/';
var _color = 'white';

var _patterns = ['base', 'bl', 'bo', 'br', 'bri', 'bs', 'bt', 'bts', 'cbo', 'cr', 'cre', 'cs', 'dls', 'drs', 'flo', 'gra', 'hh', 'ld', 'ls', 'mc', 'moj', 'mr', 'ms', 'rd', 'rs', 'sc', 'sku', 'ss', 'tl', 'tr', 'ts', 'tt', 'tts', 'vh', 'lud', 'rud', 'gru', 'hhb', 'vhr'];
var _colors = {'black':0,'red':1,'green':2,'brown':3,'blue':4,'purple':5,'cyan':6,'gray':7,'dark_gray':8,'pink':9,'lime':10,'yellow':11,'light_blue':12,'magenta':13,'orange':14,'white':15};
var _colorsInv = ['black','red','green','brown','blue','purple','cyan','gray','dark_gray','pink','lime','yellow','light_blue','magenta','orange','white'];

var _crafting = {
base:[1,1,1,
	1,1,1,
	'','stick',''],
gra:[1,'bn',1,
	'',1,'',
	'',1,''],
bri:['','','',
	'','bn',1,
	'','brick',''],
hh:[1,1,1,
	1,1,1,
	'','bn',''],
vh:[1,1,'',
	1,1,'bn',
	1,1,''],
ts:[1,1,1,
	'','bn','',
	'','',''],
bs:['','','',
	'','bn','',
	1,1,1],
ls:[1,'','',
	1,'bn','',
	1,'',''],
rs:['','',1,
	'','bn',1,
	'','',1],
ld:[1,1,'',
	1,'','',
	'','bn',''],
rud:['',1,1,
	'','',1,
	'','bn',''],
dls:['','',1,
	'',1,'',
	1,'bn',''],
drs:[1,'','',
	'',1,'',
	'','bn',1],
cr:[1,'',1,
	'',1,'',
	1,'bn',1],
sc:['',1,'',
	1,1,1,
	'bn',1,''],
cs:['',1,'',
	'',1,'bn',
	'',1,''],
ms:['','','',
	1,1,1,
	'','bn',''],
tl:[1,'','',
	'','','',
	'','bn',''],
bl:['','','',
	'','','',
	1,'bn',''],
tr:['','',1,
	'','','',
	'','bn',''],
br:['','','',
	'','','',
	'','bn',1],
tt:[1,'bn',1,
	'',1,'',
	'','',''],
bt:['','','',
	'',1,'',
	1,'bn',1],
mr:['',1,'',
	1,'bn',1,
	'',1,''],
mc:['','','',
	'',1,'',
	'','bn',''],
bts:['','','',
	1,'bn',1,
	'',1,''],
tts:['',1,'',
	1,'bn',1,
	'','',''],
ss:[1,'',1,
	1,'bn',1,
	'','',''],
bo:[1,1,1,
	1,'bn',1,
	1,1,1],
cbo:['','','',
	'','vine',1,
	'','bn',''],
flo:['','','',
	'','bn',1,
	'','flower',''],
cre:['','','',
	'','bn',1,
	'','creeper',''],
sku:['','','',
	'','bn',1,
	'','wither',''],
moj:['','','',
	'','bn',1,
	'','apple',''],
lud:['','bn','',
	1,'','',
	1,1,''],
rd:['','bn','',
	'','',1,
	'',1,1],
gru:['',1,'',
	'',1,'',
	1,'bn',1],
hhb:['','bn','',
	1,1,1,
	1,1,1],
vhr:['',1,1,
	'bn',1,1,
	'',1,1]
}

//I am VERY VERY SORRY
base = 'base';
bl = 'bl';
bo = 'bo';
br = 'br';
bri = 'bri';
bs = 'bs';
bt = 'bt';
bts = 'bts';
cbo = 'cbo';
cr = 'cr';
cre = 'cre';
cs = 'cs';
dls = 'dls';
drs = 'drs';
flo = 'flo';
gra = 'gra';
hh = 'hh';
ld = 'ld';
ls = 'ls';
mc = 'mc';
moj = 'moj';
mr = 'mr';
ms = 'ms';
rd = 'rd';
rs = 'rs';
sc = 'sc';
sku = 'sku';
ss = 'ss';
tl = 'tl';
tr = 'tr';
ts = 'ts';
tt = 'tt';
tts = 'tts';
vh = 'vh';
lud = 'lud';
rud = 'rud';
gru = 'gru';
hhb = 'hhb';
vhr = 'vhr';

function supports_html5_storage() {
  try {
    return 'localStorage' in window && window['localStorage'] !== null;
} catch (e) {
    return false;
  }
}


function banner_init() {
    _canvas = document.getElementById('canvas');
    _layers = document.getElementById('layers');
    _preview = document.getElementById('preview');
    _url = document.getElementById('url');
    _urlGal = document.getElementById('url-gal');
    _code = document.getElementById('code');
    _craft = document.getElementById('craft');
    _savedCont = document.getElementById('saved');
    _savedGal = document.getElementById('saved-gal');
    _layers.firstElementChild.onmousedown = function(event){layerHandler(event);};
    new Sortable(document.getElementById('layers'),{
            draggable:'.dgbl',
            onUpdate: function (){
                    updateDamnStupidSortableLayersLibsMustBurnInHellFFS();
            }
    });
    if (supports_html5_storage()) {
            loadLocal();
            setUrlGal();
    }
    // readUrl();
}

function newPattern(ptn,clr) {
	if (typeof ptn === 'undefined' || typeof clr === 'undefined' || _patterns.indexOf(ptn) > 39 || ptn === 'base') return;
var img, newLayer, ptn;
	img = document.createElement('i');
	img.setAttribute('ptn', ptn);
	img.setAttribute('clr', clr);
	_canvas.appendChild(img);
	newLayer = document.createElement('div');
	newLayer.className = 'layer vis dgbl';
	newLayer.innerHTML += '<div class="layer-color"></div>\n\
    <div class="layer-vis sel"></div>\n\
    <div class="layer-pattern">\n\
        <div class="layer-hover">\n\
            <div class="layer-color-container">\n\
                <div class="tb-color black" title="black"></div>\n\
                <div class="tb-color dark_gray" title="dark_gray"></div>\n\
                <div class="tb-color gray" title="gray"></div>\n\
                <div class="tb-color white" title="white"></div>\n\
                <div class="tb-color pink" title="pink"></div>\n\
                <div class="tb-color magenta" title="magenta"></div>\n\
                <div class="tb-color purple" title="purple"></div>\n\
                <div class="tb-color blue" title="blue"></div>\n\
                <div class="tb-color cyan" title="cyan"></div>\n\
                <div class="tb-color light_blue" title="light_blue"></div>\n\
                <div class="tb-color green" title="green"></div>\n\
                <div class="tb-color lime" title="lime"></div>\n\
                <div class="tb-color yellow" title="yellow"></div>\n\
                <div class="tb-color orange" title="orange"></div>\n\
                <div class="tb-color brown" title="brown"></div>\n\
                <div class="tb-color red" title="red"></div></div>\n\
                <div class="layer-pattern-container">\n\
                    <div class="tbc"><i class="tb-ptn" ptn="gra" clr="15"></i></div>\n\
                    <div class="tbc"><i class="tb-ptn" ptn="gru" clr="15"></i></div>\n\
                    <div class="tbc"><i class="tb-ptn" ptn="bri" clr="15"></i></div>\n\
                    <div class="tbc"><i class="tb-ptn" ptn="hh" clr="15"></i></div>\n\
                    <div class="tbc"><i class="tb-ptn" ptn="hhb" clr="15"></i></div>\n\
                    <div class="tbc"><i class="tb-ptn" ptn="vh" clr="15"></i></div>\n\
                    <div class="tbc"><i class="tb-ptn" ptn="vhr" clr="15"></i></div>\n\
                    <div class="tbc"><i class="tb-ptn" ptn="ts" clr="15"></i></div>\n\
                    <div class="tbc"><i class="tb-ptn" ptn="bs" clr="15"></i></div>\n\
                    <div class="tbc"><i class="tb-ptn" ptn="ls" clr="15"></i></div>\n\
                    <div class="tbc"><i class="tb-ptn" ptn="rs" clr="15"></i></div>\n\
                    <div class="tbc"><i class="tb-ptn" ptn="ld" clr="15"></i></div>\n\
                    <div class="tbc"><i class="tb-ptn" ptn="rud" clr="15"></i></div>\n\
                    <div class="tbc"><i class="tb-ptn" ptn="lud" clr="15"></i></div>\n\
                    <div class="tbc"><i class="tb-ptn" ptn="rd" clr="15"></i></div>\n\
                    <div class="tbc"><i class="tb-ptn" ptn="cr" clr="15"></i></div>\n\
                    <div class="tbc"><i class="tb-ptn" ptn="dls" clr="15"></i></div>\n\
                    <div class="tbc"><i class="tb-ptn" ptn="drs" clr="15"></i></div>\n\
                    <div class="tbc"><i class="tb-ptn" ptn="sc" clr="15"></i></div>\n\
                    <div class="tbc"><i class="tb-ptn" ptn="cs" clr="15"></i></div>\n\
                    <div class="tbc"><i class="tb-ptn" ptn="ms" clr="15"></i></div>\n\
                    <div class="tbc"><i class="tb-ptn" ptn="tl" clr="15"></i></div>\n\
                    <div class="tbc"><i class="tb-ptn" ptn="bl" clr="15"></i></div>\n\
                    <div class="tbc"><i class="tb-ptn" ptn="tr" clr="15"></i></div>\n\
                    <div class="tbc"><i class="tb-ptn" ptn="br" clr="15"></i></div>\n\
                    <div class="tbc"><i class="tb-ptn" ptn="tt" clr="15"></i></div>\n\
                    <div class="tbc"><i class="tb-ptn" ptn="bt" clr="15"></i></div>\n\
                    <div class="tbc"><i class="tb-ptn" ptn="mr" clr="15"></i></div>\n\
                    <div class="tbc"><i class="tb-ptn" ptn="mc" clr="15"></i></div>\n\
                    <div class="tbc"><i class="tb-ptn" ptn="bts" clr="15"></i></div>\n\
                    <div class="tbc"><i class="tb-ptn" ptn="tts" clr="15"></i></div>\n\
                    <div class="tbc"><i class="tb-ptn" ptn="ss" clr="15"></i></div>\n\
                    <div class="tbc"><i class="tb-ptn" ptn="bo" clr="15"></i></div>\n\
                    <div class="tbc"><i class="tb-ptn" ptn="cbo" clr="15"></i></div>\n\
                    <div class="tbc"><i class="tb-ptn" ptn="flo" clr="15"></i></div>\n\
                    <div class="tbc"><i class="tb-ptn" ptn="cre" clr="15"></i></div>\n\
                    <div class="tbc"><i class="tb-ptn" ptn="sku" clr="15"></i></div>\n\
                    <div class="tbc"><i class="tb-ptn" ptn="moj" clr="15"></i></div>\n\
                </div>\n\
            </div>\n\
        </div>\n\
        <div class="layer-craft"></div>\n\
        <div class="layer-del"></div>\n\
        <div class="layer-drag"></div>\n\
        <div class="layer-move">\n\
        <div class="layer-up"></div>\n\
        <div class="layer-down"></div>\n\
    </div>';
	img = document.createElement('i');
	img.setAttribute('ptn', ptn);
	img.setAttribute('clr', clr);
	img.classList.add('tb-ptn');
	newLayer.getElementsByClassName('layer-pattern')[0].appendChild(img);
	newLayer.getElementsByClassName('layer-color')[0].classList.add(_colorsInv[clr]);
	newLayer.getElementsByClassName('layer-color')[0].title = _colorsInv[clr];
	var patterns = newLayer.getElementsByTagName('i');
	for(i=0;i<patterns.length;i++) {
		patterns[i].setAttribute('clr', clr);
	/*	patterns[i].onmouseover = function(event){showPreview(event)};
		patterns[i].onmouseout = function(event){hidePreview(event)}; */
	}
	newLayer.getElementsByClassName('layer-craft')[0].appendChild(craftDoPtn(ptn,clr));
	_layers.appendChild(newLayer);
	_layers.lastElementChild.onmousedown = function(event){layerHandler(event)};
	layerMoveRedraw();
}

function updateLayer(ptn,clr,layer) {
var elem;
var index = Array.prototype.indexOf.call(_layers.children, layer);
	if (ptn === '') {
		ptn = _canvas.children[index].getAttribute('ptn');
	}
	if (clr === '') {
		clr = _canvas.children[index].getAttribute('clr');
	}else{
		if (typeof clr == 'string'){
			if(clr.length < 3) {
				crl = parseInt(clr, 10);
			}else{
				clr = _colors[clr];
			}
		}
	}
	_canvas.children[index].setAttribute('ptn', ptn);
	_canvas.children[index].setAttribute('clr', clr);
	layer.getElementsByClassName('layer-color')[0].className = 'layer-color '+_colorsInv[clr];
	layer.getElementsByClassName('layer-color')[0].title = _colorsInv[clr];
	elem = layer.getElementsByClassName('layer-craft')[0];
	elem.innerHTML = '';
	elem.appendChild(craftDoPtn(ptn,clr));
var patterns = layer.getElementsByTagName('i');
	if (patterns[patterns.length-1].getAttribute('clr') != clr) {
		for(i=0;i<patterns.length;i++) {
			patterns[i].setAttribute('clr', clr);
		}
	}
	patterns[patterns.length-1].setAttribute('ptn', ptn);
}

//Is it okay to swear in code?
function updateDamnStupidSortableLayersLibsMustBurnInHellFFS() {
	for (var i=1;i<_layers.children.length;i++) {
		
		updateLayer(_layers.children[i].getElementsByClassName('layer-pattern')[0].lastElementChild.getAttribute('ptn'),_layers.children[i].getElementsByClassName('layer-pattern')[0].lastElementChild.getAttribute('clr'),_layers.children[i])
		if(_layers.children[i].classList.contains('vis')) {
			_canvas.children[i].classList.remove('hidden');
		}else{
			_canvas.children[i].classList.add('hidden');
		};
	}
	layerMoveRedraw();
	setUrl();
}

function patternsHandler(event) {
	if (event.target.tagName == 'I'){
		var ptn = event.target.getAttribute('ptn');
		var clr = _colors[_color];
		newPattern(ptn, clr);
		_preview.innerHTML = '';
		setUrl();
	}
}

function showPreview(event) {
	if (event.target.tagName == 'I'){
		var ptn = event.target.getAttribute('ptn');
		var clr = _colors[_color];
		img = document.createElement('i');
		img.setAttribute('ptn', ptn);
		img.setAttribute('clr', clr);
		_preview.appendChild(img);
	}
}

function hidePreview(event) {
	_preview.innerHTML = '';
}

function colorsHandler(event) {
var toolbar, i;
	if (event.target.classList[0] == 'tb-color'){
		for (i=0;i<event.target.parentNode.childElementCount;i++) {
			event.target.parentNode.children[i].classList.remove('sel');
		}
		event.target.classList.add('sel');
		_color = event.target.classList[1];
		var patterns = document.getElementById('toolbar-patterns').getElementsByTagName('i');
		for(i=0;i<patterns.length;i++) {
			patterns[i].setAttribute('clr', _colors[_color]);
		}
	}
}

function layerHandler(event) {
var index, layer, i, color, elem;
	switch(event.target.classList[0]){
		case 'tb-color':
			layer = event.target.parentNode.parentNode.parentNode.parentNode;
			color = event.target.classList[1];
			updateLayer('',color,layer);
			break;
		case 'tb-ptn':
			layer = event.target.parentNode.parentNode.parentNode.parentNode.parentNode;
			if(event.target.parentNode != 'layer-hover') updateLayer(event.target.getAttribute('ptn'),'',layer);
			break;
		case 'layer-del':
			layer = event.target.parentNode;
			index = Array.prototype.indexOf.call(_layers.children, layer);
			_layers.removeChild(_layers.children[index]);
			_canvas.removeChild(_canvas.children[index]);
			layerMoveRedraw();
			break;
		case 'layer-up':
			layer = event.target.parentNode.parentNode;
			index = Array.prototype.indexOf.call(_layers.children, layer);
			if(index > 1) {
				_layers.children[index].parentNode.insertBefore(_layers.children[index], _layers.children[index-1]);
				_canvas.children[index].parentNode.insertBefore(_canvas.children[index], _canvas.children[index-1]);
				layerMoveRedraw();
			}
			break;
		case 'layer-down':
			layer = event.target.parentNode.parentNode;
			index = Array.prototype.indexOf.call(_layers.children, layer);
			if (index < _layers.children.length-1){
				_layers.children[index+1].parentNode.insertBefore(_layers.children[index+1], _layers.children[index]);
				_canvas.children[index+1].parentNode.insertBefore(_canvas.children[index+1], _canvas.children[index]);
				layerMoveRedraw();
			}
			break;
		case 'layer-vis':
			layer = event.target.parentNode;
			index = Array.prototype.indexOf.call(_layers.children, layer);
			event.target.classList.toggle('sel');
			_canvas.children[index].classList.toggle('hidden');
			layer.classList.toggle('vis');
	}
	setUrl();
}

function layerMoveRedraw() {
var	elem = _layers.getElementsByClassName('layer-move');
	if(elem.length>1){
		for(i=0;i<elem.length;i++){
			if(i<elem.length-1) elem[i+1].children[0].classList.remove('hidden');
			if(i>0) elem[i-1].children[1].classList.remove('hidden');
		}
	}
	if(elem.length>0){
		elem[0].children[0].classList.add('hidden');
		elem[elem.length-1].children[1].classList.add('hidden');
	}
	//new Sortable(_layers);
}

function getNBT(){
var obj = {};
	obj.Base = _canvas.children[0].getAttribute('clr');
	obj.Patterns = [];
	for (var i=1;i<_canvas.children.length;i++){
		if(!_canvas.children[i].classList.contains('hidden')) obj.Patterns.push({Pattern:_canvas.children[i].getAttribute('ptn'),Color:_canvas.children[i].getAttribute('clr')});
	}
	return obj;
}

function jsonOutput(mode) {
var ret;
_jsonMode = mode;
var obj = getNBT();
	switch(_jsonMode){
		case 0:
			ret = '/give @p minecraft:banner 1 0 '+JSON.stringify({BlockEntityTag:obj});
			break;
		case 1:
			ret = '/setblock ~ ~1 ~ minecraft:standing_banner 0 replace '+JSON.stringify(obj);
			break;
		case 2:
			ret = '/replaceitem entity @p slot.armor.head minecraft:banner 1 0 '+JSON.stringify({BlockEntityTag:obj});
			break;
		case 3:
			ret = JSON.stringify({BlockEntityTag:obj},'',1);
			break;
	}
	ret = ret.replace(/"/g,'');
	_code.value = ret;
}

function jsonInput() {
var i, elem;
var val = document.getElementById('code').value;
	val = val.replace(/[\r\n\s\t]+?/g,'');
var rgx = /{(.*)}/g;
	val = rgx.exec(val)[0];
	val = val.toLowerCase();
var obj = eval('('+val+')');
	if(typeof obj.blockentitytag !== 'undefined') {
		obj = obj.blockentitytag;
	}
	if(obj.base == undefined) {
		obj.base = 15;
	}
	clearAll();
	updateLayer('base',obj.base,_layers.firstElementChild);
	for(i=0;i<obj.patterns.length;i++){
		newPattern(obj.patterns[i].pattern,obj.patterns[i].color)
	}
	setUrl();
}

function clearAll() {
	for (var i=1;i<_canvas.children.length;i++){
		i--;
		_canvas.removeChild(_canvas.children[1]);
		_layers.removeChild(_layers.children[1]);
	}
	updateLayer('base',0,_layers.firstElementChild);
	setUrl();
}

function updateTip() {
var layers = _layers.getElementsByClassName('vis');
	for (var i=1;i<_layers.children.length;i++) {
		_layers.children[i].classList.remove('overflow');
		_layers.children[i].classList.remove('tip');
	}
	for (var i=6;i<layers.length;i++) {
		if(i==6) layers[i].classList.add('tip');
		layers[i].classList.add('overflow');
	}
}

function setUrl(){
var hash = '';
var obj = getNBT();
	/*if(obj.Patterns.length>0){
		hash = obj.Base;
		for(var i=0;i<obj.Patterns.length;i++) {
			hash += '.'+obj.Patterns[i].Color+'-'+obj.Patterns[i].Pattern;
		}
		_url.value = window.location.protocol+'//'+window.location.hostname+window.location.pathname+'?='+hash;
		window.history.pushState('Minecraft Banner Generator', 'Minecraft Banner Generator', window.location.pathname+'?='+hash);
	}else{
		_url.value = '';
		hash = '';
		window.history.pushState('Minecraft Banner Generator', 'Minecraft Banner Generator', window.location.pathname);
	}*/
	hash += encPair(obj.Base,'base');
	if(obj.Patterns.length>0){
		for(var i=0;i<obj.Patterns.length;i++) {
			hash += encPair(obj.Patterns[i].Color,obj.Patterns[i].Pattern);
		}
		_url.value = window.location.protocol+'//'+window.location.hostname+window.location.pathname+'?='+hash;
		window.history.pushState('Minecraft Banner Generator', 'Minecraft Banner Generator', window.location.pathname+'?='+hash);
	}else{
		_url.value = '';
		hash = '';
		window.history.pushState('Minecraft Banner Generator', 'Minecraft Banner Generator', window.location.pathname);
	}
	if(typeof _jsonMode !== 'undefined') jsonOutput(_jsonMode);
	updateTip();
}

function setUrlGal(){
if (!supports_html5_storage()) return;
if (localStorage.getObject('bn') == null) return;
var i;
var hash = '';
var _savedTemp = localStorage.getObject('bn');
	if (_savedTemp.length > 0) {
		for(i=0;i<_savedTemp.length;i++) {
			if(_savedTemp[i].Patterns.length>0){
				hash += encPair(_savedTemp[i].Base,'base');
				for(var k=0;k<_savedTemp[i].Patterns.length;k++) {
					hash += encPair(_savedTemp[i].Patterns[k].Color,_savedTemp[i].Patterns[k].Pattern);
				}
				if (i != _savedTemp.length-1) hash += '_';
			}
		}
		_urlGal.value = window.location.protocol+'//'+window.location.hostname+window.location.pathname+'?='+hash;
	}else{
		_urlGal.value = '';
	}
}

function readUrl() {
var ptns, i, k, d, dd;
var hash = window.location.search;
var obj = {};
	if(hash.length<3) {
		updateLayer('base',0,_layers.firstElementChild);
		return;
	}
	hash = hash.substr(2);
	if (hash == 'aajvayasjgaiah') alert('Are you serious? Sigh...');
	if (hash == 'aajvayasjFaiaG') alert('Why would you do that? :(');
	ptns = hash.split('_');
	if (ptns[0].indexOf('.') !== -1) {
		for(i=0;i<ptns.length;i++) {
			ptns[i] = ptns[i].split('.'); 
			for(k=0; k<ptns[i].length; k++) {
				ptns[i][k] = ptns[i][k].split('-');
			}
			ptns[i][0][1] = 'base';
		}
	}else{
		for(i=0;i<ptns.length;i++) {
			ptns[i] = ptns[i].match(/.{2}/g); 
			for(k=0; k<ptns[i].length; k++) {
				ptns[i][k] = decPair(ptns[i][k]);
			}
		}
	}
	_savedTemp = ptns;
	obj.base = ptns[0][0][0];
	obj.patterns = ptns[0];
	updateLayer('base',obj.base,_layers.firstElementChild);	
	for(i=1;i<ptns[0].length;i++){
		newPattern(ptns[0][i][1],ptns[0][i][0])
	}
	if (ptns.length > 0) {
		document.getElementById('saved-gal-cont').className = '';
		for(var i=0;i<ptns.length;i++) {
		var div = document.createElement('div');
			div.className = 'saved-unit';
		var pt = document.createElement('i');
			pt.className = 'tb-ptn';
			pt.setAttribute('ptn','base');
			pt.setAttribute('clr',ptns[i][0][0]);
			div.appendChild(pt);
			for(var k=0;k<ptns[i].length;k++) {
				pt = document.createElement('i');
				pt.className = 'tb-ptn';
				pt.setAttribute('ptn',ptns[i][k][1]);
				pt.setAttribute('clr',ptns[i][k][0]);
				div.appendChild(pt);
			}
			_savedGal.appendChild(div);
		}
	}
	updateTip();
}

function chaosInt(min, max) {
  return Math.floor(Math.random() * (max+1 - min)) + min;
}

function chaos() {
var i;
	clearAll();
var baseclr = chaosInt(0,15);
	updateLayer('base',baseclr,_layers.firstElementChild);
var ptns = document.getElementById('toolbar-patterns').getElementsByTagName('i');
	for(i=0;i<6;i++){
		newPattern(ptns[chaosInt(0,ptns.length-1)].getAttribute('ptn'),chaosInt(0,15))
	}
	setUrl();
}

function craftDoPtn(ptn,clr) {
	if (typeof _crafting[ptn] === 'undefined') return;
var	out = _crafting[ptn].slice();
	if(typeof(clr) == 'number') clr = _colorsInv[clr];
	if(clr.length < 3) clr = _colorsInv[clr];
	for (var i=0;i<out.length;i++) {
		if (out[i] == 1) {
			if ((out.indexOf('brick') > -1 || out.indexOf('vine') > -1 || out.indexOf('creeper') > -1 || out.indexOf('wither') > -1 || out.indexOf('flower') > -1 || out.indexOf('apple') > -1) && (clr == 'black' || clr == '0')) {
				out[i] = '';
			}else{
				out[i] = clr;
			}
		}
	}
	table = document.createElement('table');
	table.className = 'craft-t';
	table.innerHTML = '<tr><td><div></div></td><td><div></div></td><td><div></div></td></tr><tr><td><div></div></td><td><div></div></td><td><div></div></td></tr><tr><td><div></div></td><td><div></div></td><td><div></div></td></tr>';
	tds = table.getElementsByTagName('td');
	for (var i=0;i<out.length;i++) {
		if(out[i] != '') tds[i].classList.add(out[i]);
	}
	return table;
}

function screenshot() {
var	scrCon = document.getElementById('screenshot');
var hiddens = scrCon.querySelectorAll('.button, #saved-gal-cont, .layer-move, .layer-del, .layer-vis, .layer-drag, .header-button');
	for (var i = 0; i<hiddens.length;i++) {
		hiddens[i].classList.add('hiddeno');
	}
	html2canvas(scrCon,{
		onrendered: function(canvas) {
		var elem = document.createElement('a');
			elem.href = canvas.toDataURL('image/png');
			elem.setAttribute('download','download');
			elem.innerHTML = '<div class="button"><img src="/bngn/img/dnld.png" style="vertical-align:middle"></div>';
			document.getElementById('scrdl').innerHTML = '';
			document.getElementById('scrdl').appendChild(elem);
			document.getElementById('scrdl').classList.remove('w');
			setTimeout(function(){document.getElementById('scrdl').classList.add('w')},1)
			for (var i = 0; i<hiddens.length;i++) {
				hiddens[i].classList.remove('hiddeno');
			}
		}
    })
}

if (supports_html5_storage()) {

	Storage.prototype.setObject = function(key, value) {
		this.setItem(key, JSON.stringify(value));
	}

	Storage.prototype.getObject = function(key) {
		var value = this.getItem(key);
		return value && JSON.parse(value);
	}

	function loadLocal() {
		if (localStorage.getObject('bn') == null) return;
		_saved = localStorage.getObject('bn');
		for(var i=0;i<_saved.length;i++) {
			newSaved(_saved[i]);
		}
	}

	function newSaved(obj) {
	var div = document.createElement('div');
		div.className = 'saved-unit';
		div.innerHTML = '<div class="saved-unit-tool"><div class="saved-unit-del"></div></div>';
	var pt = document.createElement('i');
		pt.className = 'tb-ptn';
		pt.setAttribute('ptn','base');
		pt.setAttribute('clr',obj.Base);
		div.appendChild(pt);
		for(var i=0;i<obj.Patterns.length;i++) {
			pt = document.createElement('i');
			pt.className = 'tb-ptn';
			pt.setAttribute('ptn',obj.Patterns[i].Pattern);
			pt.setAttribute('clr',obj.Patterns[i].Color);
			div.appendChild(pt);
		}
		_savedCont.insertBefore(div, _savedCont.lastElementChild);
	}

	function addLocal() {
	var obj = getNBT();
		_saved.push(obj);
		localStorage.setObject('bn',_saved);
		newSaved(obj);
	}

	function delLocal(index) {
		_saved.splice(index,1);
		localStorage.setObject('bn',_saved);
		_savedCont.removeChild(_savedCont.children[index]);
	}

	function savedHandler(event) {
		switch(event.target.className) {
			case 'saved-add':
				addLocal();
				break;
			case 'saved-unit-del':
				delLocal(Array.prototype.indexOf.call(_savedCont.children, event.target.parentNode.parentNode));
				break;
			case 'tb-ptn':
				index = Array.prototype.indexOf.call(_savedCont.children, event.target.parentNode);
				clearAll();
				updateLayer('base',_saved[index].Base,_layers.firstElementChild);
				for(var i=0;i<_saved[index].Patterns.length;i++){
					newPattern(_saved[index].Patterns[i].Pattern,_saved[index].Patterns[i].Color);
				}	
		}
		setUrlGal();
		setUrl();
	}

	function savedGalHandler(event) {
		switch(event.target.className) {
			case 'tb-ptn':
				index = Array.prototype.indexOf.call(_savedGal.children, event.target.parentNode);
				clearAll();
				updateLayer('base',_savedTemp[index][0][0],_layers.firstElementChild);
				for(var i=1;i<_savedTemp[index].length;i++){
					newPattern(_savedTemp[index][i][1],_savedTemp[index][i][0]);
				}
				setUrl();
		}
	}
}

function encPair(color, pattern) {
	pattern = _patterns.indexOf(pattern);
var first = ((pattern >> 6) << 4) | (color & 0xF);
var second = pattern & 0x3F;

	return base64dict[first] + base64dict[second];
}

function decPair(value) {
var first = base64dict.indexOf(value.charAt(0));
var	second = base64dict.indexOf(value.charAt(1))
var color = first & 0xF;
var pattern = _patterns[(first >> 4) << 6 | second];
	return [color, pattern];
}

function compact(event) {
	if (event.target.classList.contains('on')) {
		event.target.parentNode.classList.remove('compact');
		event.target.classList.remove('on');
	}else{
		event.target.parentNode.classList.add('compact');
		event.target.classList.add('on');
	}
}

function donatePopup() {
	document.getElementsByClassName('donate-w')[0].classList.remove('hidden');
	document.onmousedown = function(event){
		if(event.target.classList.contains('donate-w') || event.target.classList.contains('no')) return;
		document.getElementsByClassName('donate-w')[0].classList.add('hidden');
		document.onmousedown = '';
	}
}