// Sky view for the city page and the start page: what you see facing one direction, at one moment of the night.
// Planets and the Moon come from the night track (#sky-data); bright stars and constellations
// are computed here from their fixed positions, so they need no data from the server.
(function () {
  'use strict';
  var dataEl = document.getElementById('sky-data');
  var view = document.querySelector('[data-sky]');
  if (!dataEl || !view) return;
  var D;
  try { D = JSON.parse(dataEl.textContent); } catch (e) { return; }

  var stage = view.querySelector('.skyview-stage');
  var facingEl = view.querySelector('.facing');
  // the home page shows the view alone; the city page adds time controls and the planner
  var controls = document.querySelector('[data-sky-controls]') || document.createElement('div');
  var dummy = function (tag) { return controls.querySelector(tag) || document.createElement('span'); };
  var range = document.getElementById('sky-range') || Object.assign(document.createElement('input'), { value: String(D.start) });
  var out = dummy('.sky-time');
  var nowEl = dummy('.sky-now');
  var play = dummy('.sky-play');
  var planners = Array.prototype.slice.call(document.querySelectorAll('.planner svg'));
  var L = D.labels;
  var NS = 'http://www.w3.org/2000/svg';
  var RAD = Math.PI / 180;
  var DIRS = ['N', 'NE', 'E', 'SE', 'S', 'SW', 'W', 'NW'];
  var PLANETS = ['mercury', 'venus', 'mars', 'jupiter', 'saturn'];
  var MIN_ALT = { mercury: 5 };

  // ---- bright stars: [id, RA hours, Dec degrees, magnitude] (J2000) ----
  var STARS = [
    ['sirius', 6.7525, -16.716, -1.46], ['canopus', 6.3992, -52.696, -0.74], ['arcturus', 14.2611, 19.182, -0.05],
    ['vega', 18.6156, 38.784, 0.03], ['capella', 5.2781, 45.998, 0.08], ['rigel', 5.2423, -8.202, 0.13],
    ['procyon', 7.6550, 5.225, 0.34], ['betelgeuse', 5.9195, 7.407, 0.5], ['achernar', 1.6286, -57.237, 0.46],
    ['hadar', 14.0637, -60.373, 0.61], ['altair', 19.8464, 8.868, 0.77], ['acrux', 12.4433, -63.099, 0.77],
    ['aldebaran', 4.5987, 16.509, 0.85], ['antares', 16.4901, -26.432, 1.06], ['spica', 13.4199, -11.161, 0.97],
    ['pollux', 7.7553, 28.026, 1.14], ['fomalhaut', 22.9608, -29.622, 1.16], ['deneb', 20.6905, 45.280, 1.25],
    ['mimosa', 12.7954, -59.689, 1.25], ['regulus', 10.1395, 11.967, 1.35], ['castor', 7.5767, 31.888, 1.58],
    ['shaula', 17.5601, -37.104, 1.62], ['gacrux', 12.5194, -57.113, 1.63], ['bellatrix', 5.4189, 6.350, 1.64],
    ['elnath', 5.4382, 28.608, 1.65], ['alnilam', 5.6036, -1.202, 1.69], ['alnitak', 5.6793, -1.943, 1.77],
    ['mintaka', 5.5334, -0.299, 2.23], ['saiph', 5.7959, -9.670, 2.06], ['meissa', 5.5856, 9.934, 3.39],
    ['polaris', 2.5303, 89.264, 1.98], ['dubhe', 11.0621, 61.751, 1.79], ['merak', 11.0307, 56.382, 2.37],
    ['phecda', 11.8972, 53.695, 2.44], ['megrez', 12.2571, 57.033, 3.31], ['alioth', 12.9005, 55.960, 1.77],
    ['mizar', 13.3988, 54.925, 2.27], ['alkaid', 13.7923, 49.313, 1.86], ['caph', 0.1530, 59.150, 2.27],
    ['schedar', 0.6751, 56.537, 2.24], ['gcas', 0.9451, 60.717, 2.47], ['ruchbah', 1.4303, 60.235, 2.68],
    ['segin', 1.9066, 63.670, 3.38], ['denebola', 11.8177, 14.572, 2.14], ['algieba', 10.3329, 19.842, 2.08],
    ['zosma', 11.2351, 20.524, 2.56], ['chertan', 11.2373, 15.430, 3.33], ['adhafera', 10.2782, 23.417, 3.4],
    ['rasalas', 9.8794, 26.007, 3.88], ['algenubi', 9.7642, 23.774, 2.98], ['etaleo', 10.1222, 16.763, 3.5],
    ['dschubba', 16.0056, -22.622, 2.32], ['acrab', 16.0906, -19.806, 2.62], ['pisco', 15.9809, -26.114, 2.89],
    ['tausco', 16.5980, -28.216, 2.82], ['epssco', 16.8361, -34.293, 2.29], ['mu1sco', 16.8645, -38.047, 3.0],
    ['zeta2sco', 16.9097, -42.362, 3.6], ['etasco', 17.2026, -43.239, 3.3], ['sargas', 17.6219, -42.998, 1.86],
    ['iota1sco', 17.7931, -40.127, 3.0], ['kappasco', 17.7081, -39.030, 2.39], ['sadr', 20.3705, 40.257, 2.23],
    ['albireo', 19.5120, 27.960, 3.08], ['gienah', 20.7702, 33.970, 2.48], ['deltacyg', 19.7496, 45.131, 2.87],
    ['deltacru', 12.2524, -58.749, 2.79], ['alcyone', 3.7914, 24.105, 2.87],
    ['alpheratz', 0.1398, 29.091, 2.06], ['scheat', 23.0629, 28.083, 2.42], ['markab', 23.0794, 15.205, 2.49],
    ['algenib', 0.2206, 15.184, 2.83], ['mirach', 1.1622, 35.621, 2.05], ['almach', 2.0650, 42.330, 2.1],
    ['hamal', 2.1196, 23.462, 2.0], ['diphda', 0.7265, -17.987, 2.04], ['mirfak', 3.4054, 49.861, 1.79],
    ['algol', 3.1361, 40.956, 2.1], ['enif', 21.7364, 9.875, 2.38]
  ];
  var FIGURES = {
    orion: [['betelgeuse', 'meissa', 'bellatrix'], ['betelgeuse', 'alnitak'], ['bellatrix', 'mintaka'],
            ['alnitak', 'alnilam', 'mintaka'], ['alnitak', 'saiph'], ['mintaka', 'rigel']],
    ursa_major: [['dubhe', 'merak', 'phecda', 'megrez', 'dubhe'], ['megrez', 'alioth', 'mizar', 'alkaid']],
    cassiopeia: [['caph', 'schedar', 'gcas', 'ruchbah', 'segin']],
    summer_triangle: [['vega', 'deneb', 'altair', 'vega']],
    cygnus: [['deneb', 'sadr', 'albireo'], ['gienah', 'sadr', 'deltacyg']],
    leo: [['regulus', 'etaleo', 'algieba', 'adhafera', 'rasalas', 'algenubi'], ['algieba', 'zosma', 'denebola', 'chertan', 'regulus'], ['zosma', 'chertan']],
    scorpius: [['acrab', 'dschubba', 'pisco'], ['dschubba', 'antares', 'tausco', 'epssco', 'mu1sco', 'zeta2sco', 'etasco', 'sargas', 'iota1sco', 'kappasco', 'shaula']],
    crux: [['gacrux', 'acrux'], ['mimosa', 'deltacru']],
    gemini: [['castor', 'pollux']],
    pleiades: [['alcyone']],
    great_square: [['alpheratz', 'scheat', 'markab', 'algenib', 'alpheratz'], ['alpheratz', 'mirach', 'almach']],
    perseus: [['algol', 'mirfak']]
  };

  // ---- astronomy ----
  function starHorizon(raH, decD, date) {
    var jd = date.getTime() / 86400000 + 2440587.5;
    var gmst = 280.46061837 + 360.98564736629 * (jd - 2451545);
    var ha = ((gmst + D.lon - raH * 15) % 360 + 360) % 360 * RAD;
    var lat = D.lat * RAD, dec = decD * RAD;
    var sinAlt = Math.sin(lat) * Math.sin(dec) + Math.cos(lat) * Math.cos(dec) * Math.cos(ha);
    var alt = Math.asin(sinAlt);
    var az = Math.atan2(-Math.cos(dec) * Math.sin(ha), Math.sin(dec) * Math.cos(lat) - Math.cos(dec) * Math.sin(lat) * Math.cos(ha));
    return { alt: alt / RAD, az: ((az / RAD) % 360 + 360) % 360 };
  }

  // ---- ground: a low, fixed skyline of trees and roofs all around ----
  function rnd(k) { var x = Math.sin(k * 12.9898 + 78.233) * 43758.5453; return x - Math.floor(x); }
  function ground(az) {
    az = ((az % 360) + 360) % 360;
    var h = 0.9 + 0.35 * Math.sin(az * RAD * 8) + 0.25 * Math.sin(az * RAD * 19 + 1.3);
    var k = Math.floor(az / 6), f = (az - k * 6) / 6, r = rnd(k);
    if (r > 0.72) {                    // a roof: walls, then a gable
      var top = 2.2 + rnd(k + 99) * 1.4;
      if (f > 0.1 && f < 0.9) h = Math.max(h, top + (0.5 - Math.abs(f - 0.5)) * 1.6);
    } else if (r > 0.4) {              // a tree crown
      var th = 1.8 + rnd(k + 51) * 2.2;
      h = Math.max(h, th * Math.sqrt(Math.max(0, 1 - Math.pow((f - 0.5) * 2.1, 2))));
    }
    return h;
  }

  // ---- drawing helpers ----
  var SKY = [[0, [74, 102, 160], [214, 166, 132]], [-6, [38, 52, 102], [150, 118, 128]],
             [-12, [24, 34, 74], [62, 76, 128]], [-18, [14, 20, 48], [32, 42, 82]]];
  function mix(a, b, f) { return a.map(function (v, k) { return Math.round(v + (b[k] - v) * f); }); }
  function skyColours(s) {
    if (s >= SKY[0][0]) return [SKY[0][1], SKY[0][2]];
    for (var i = 0; i < SKY.length - 1; i++) {
      var a = SKY[i], b = SKY[i + 1];
      if (s <= a[0] && s >= b[0]) { var f = (a[0] - s) / (a[0] - b[0]); return [mix(a[1], b[1], f), mix(a[2], b[2], f)]; }
    }
    return [SKY[3][1], SKY[3][2]];
  }
  function rgb(c) { return 'rgb(' + c.join(',') + ')'; }
  function el(name, attrs, text) {
    var n = document.createElementNS(NS, name);
    for (var k in attrs) n.setAttribute(k, attrs[k]);
    if (text !== undefined) n.textContent = text;
    return n;
  }
  function dirOf(az) { return DIRS[Math.floor(((az % 360) + 382.5) / 45) % 8]; }
  function height(alt) {
    var n = Math.round(alt / 10);
    if (n < 1) return L.text.fist_low;
    return n === 1 ? L.text.fist_one : L.text.fists.replace('{n}', n);
  }
  function moonShape(cx, cy, r, illum, waxing) {
    var g = el('g', { 'class': 'moon' });
    g.appendChild(el('circle', { cx: cx, cy: cy, r: r, 'class': 'moon-dark' }));
    var right = D.lat < 0 ? !waxing : waxing;
    var k = Math.max(0, Math.min(1, illum)), rx = Math.abs(1 - 2 * k) * r;
    var top = cx + ' ' + (cy - r), bottom = cx + ' ' + (cy + r);
    g.appendChild(el('path', { 'class': 'moon-lit',
      d: 'M' + top + ' A' + r + ' ' + r + ' 0 0 ' + (right ? 1 : 0) + ' ' + bottom + ' A' + rx + ' ' + r + ' 0 0 ' + ((k < 0.5) === right ? 0 : 1) + ' ' + top }));
    return g;
  }

  // ---- state ----
  var facing = 180, manual = false, index = +range.value, timer = null, focus = null;

  function bodiesAt(i) {
    var list = [];
    PLANETS.concat(['moon']).forEach(function (name) {
      var b = D.bodies[name];
      if (!b || b.alt[i] <= 0) return;
      if (name === 'moon' && b.illumination < D.moonMin) return;
      list.push({ name: name, alt: b.alt[i], az: b.az[i], mag: b.magnitude });
    });
    return list;
  }

  function bestFacing(i, fov) {
    var best = facing, bestScore = -1;
    for (var k = 0; k < 8; k++) {
      var F = k * 45, score = 0;
      bodiesAt(i).forEach(function (b) {
        var d = Math.abs(((b.az - F + 540) % 360) - 180);
        if (d <= fov / 2 - 8) score += b.name === 'moon' ? 1 : (b.alt >= (MIN_ALT[b.name] || 10) ? 3 : 1);
      });
      if (F === facing) score += 0.5;          // don't turn for nothing
      if (score > bestScore) { bestScore = score; best = F; }
    }
    return best;
  }

  function draw() {
    var i = index;
    // drawn at the real size of the stage, so text and dots keep their size on any screen
    var w = Math.max(280, Math.round(stage.clientWidth || 800));
    var narrow = w < 520;
    var maxH = Math.max(240, Math.round((window.innerHeight || 800) * (narrow ? 0.6 : 0.5)));
    var h = Math.min(narrow ? Math.round(w * 0.82) : Math.max(260, Math.round(w * 0.45)), maxH, 470);
    var fov = Math.max(90, Math.min(130, Math.round(w / h * 45 / 5) * 5));
    if (!manual) facing = bestFacing(i, fov);
    var horizon = h - 34, top = 14;
    var X = function (az) { var d = ((az - facing + 540) % 360) - 180; return w / 2 + d / (fov / 2) * (w / 2); };
    var inView = function (az, m) { return Math.abs(((az - facing + 540) % 360) - 180) <= fov / 2 + (m || 0); };
    var Y = function (alt) { return horizon - Math.max(0, Math.min(90, alt)) / 90 * (horizon - top); };
    var sunAlt = D.sun[i], sunAz = D.sunAz[i];
    var cols = skyColours(sunAlt);
    var when = new Date(D.iso[i]);

    var svg = el('svg', { viewBox: '0 0 ' + w + ' ' + h, width: w, height: h });
    var defs = el('defs');
    var g1 = el('linearGradient', { id: 'sv-sky', x1: 0, y1: 0, x2: 0, y2: 1 });
    g1.appendChild(el('stop', { offset: 0, 'stop-color': rgb(cols[0]) }));
    g1.appendChild(el('stop', { offset: 1, 'stop-color': rgb(cols[1]) }));
    defs.appendChild(g1);
    var glow = Math.max(0, Math.min(1, (sunAlt + 16) / 16)) * 0.6;
    var sunD = ((sunAz - facing + 540) % 360) - 180;
    var g2 = el('radialGradient', { id: 'sv-glow', gradientUnits: 'userSpaceOnUse',
      cx: (w / 2 + sunD / (fov / 2) * (w / 2)).toFixed(1), cy: horizon, r: (w * 0.75).toFixed(1) });
    g2.appendChild(el('stop', { offset: 0, 'stop-color': '#F0A878', 'stop-opacity': glow }));
    g2.appendChild(el('stop', { offset: 1, 'stop-color': '#F0A878', 'stop-opacity': 0 }));
    defs.appendChild(g2);
    svg.appendChild(defs);
    svg.appendChild(el('rect', { x: 0, y: 0, width: w, height: horizon, fill: 'url(#sv-sky)' }));
    if (glow > 0.01 && Math.abs(sunD) < 150) {
      svg.appendChild(el('rect', { x: 0, y: 0, width: w, height: horizon, fill: 'url(#sv-glow)' }));
    }

    // fist ruler on the left
    for (var a = 10; a <= 80; a += 10) {
      svg.appendChild(el('line', { 'class': 'sv-tick' + (a % 20 === 0 ? ' major' : ''), x1: 0, x2: a % 20 === 0 ? 14 : 8, y1: Y(a), y2: Y(a) }));
      if (a % 20 === 0) svg.appendChild(el('text', { 'class': 'sv-ruler', x: 18, y: Y(a) + 4 }, L.text.ruler.replace('{n}', a / 10)));
    }

    var planetSpots = bodiesAt(i).filter(function (b) { return inView(b.az, 4); })
      .map(function (b) { return [X(b.az), Y(b.alt)]; });
    var nearPlanet = function (x, y) {
      return planetSpots.some(function (q) { return Math.abs(q[0] - x) < 90 && Math.abs(q[1] - y) < 34; });
    };

    // stars and constellations, faded in as the sky darkens
    var starAlpha = Math.max(0, Math.min(1, (-sunAlt - 5) / 9));
    var pos = {};
    if (starAlpha > 0) {
      STARS.forEach(function (s) {
        var p = starHorizon(s[1], s[2], when);
        p.mag = s[3];
        pos[s[0]] = p;
      });
      var gl = el('g', { 'class': 'sv-figures', opacity: starAlpha });
      Object.keys(FIGURES).forEach(function (fig) {
        var pts = [];
        FIGURES[fig].forEach(function (line) {
          for (var k = 1; k < line.length; k++) {
            var a = pos[line[k - 1]], b = pos[line[k]];
            if (a.alt > 0 && b.alt > 0 && inView(a.az, 5) && inView(b.az, 5) &&
                Math.abs(((a.az - b.az + 540) % 360) - 180) < 60) {
              gl.appendChild(el('line', { x1: X(a.az).toFixed(1), y1: Y(a.alt).toFixed(1), x2: X(b.az).toFixed(1), y2: Y(b.alt).toFixed(1) }));
            }
          }
          line.forEach(function (id) { if (pos[id].alt > 2 && inView(pos[id].az)) pts.push(pos[id]); });
        });
        if (pts.length >= Math.min(2, FIGURES[fig][0].length)) {
          var cx = pts.reduce(function (s, p) { return s + X(p.az); }, 0) / pts.length;
          var cy = pts.reduce(function (s, p) { return s + Y(p.alt); }, 0) / pts.length;
          var low = Math.max.apply(null, pts.map(function (p) { return Y(p.alt); }));
          var ly = Math.min(horizon - 8, low + 22);
          if (!nearPlanet(cx, ly)) {
            gl.appendChild(el('text', { 'class': 'sv-const', x: cx.toFixed(1), y: ly.toFixed(1), 'text-anchor': 'middle' }, L.const[fig]));
          }
        }
      });
      svg.appendChild(gl);
      var gs = el('g', { 'class': 'sv-stars', opacity: starAlpha });
      STARS.forEach(function (s) {
        var p = pos[s[0]];
        if (p.alt <= ground(p.az) || !inView(p.az)) return;
        var r = Math.max(1.2, 3.4 - 0.62 * p.mag) * (narrow ? 0.85 : 1);
        if (p.mag < 1) gs.appendChild(el('circle', { 'class': 'glow', cx: X(p.az).toFixed(1), cy: Y(p.alt).toFixed(1), r: (r * 2.6).toFixed(1) }));
        gs.appendChild(el('circle', { cx: X(p.az).toFixed(1), cy: Y(p.alt).toFixed(1), r: r.toFixed(2) }));
        if (L.star[s[0]] && p.mag < 1.6 && !narrow && !nearPlanet(X(p.az), Y(p.alt))) {
          gs.appendChild(el('text', { 'class': 'sv-star-name', x: (X(p.az) + r + 4).toFixed(1), y: (Y(p.alt) + 4).toFixed(1) }, L.star[s[0]]));
        }
      });
      svg.appendChild(gs);
    }

    // planets and the Moon, labels kept apart
    var up = bodiesAt(i).filter(function (b) { return inView(b.az, 4); }).map(function (b) {
      b.x = X(b.az); b.y = Y(b.alt); return b;
    });
    var placed = up.map(function (p) { var r = p.name === 'moon' ? 13 : 9; return [p.x - r, p.y - r, p.x + r, p.y + r]; });
    up.slice().sort(function (a, b) { return b.alt - a.alt; }).forEach(function (p) {
      var text = L.planet[p.name], tw = text.length * 8.6, r = p.name === 'moon' ? 12 : 7;
      var opts = [[p.x, p.y - r - 8, 'middle'], [p.x, p.y + r + 18, 'middle'], [p.x + r + 6, p.y + 5, 'start'],
        [p.x - r - 6, p.y + 5, 'end'], [p.x + r + 4, p.y - r - 6, 'start'], [p.x - r - 4, p.y - r - 6, 'end'],
        [p.x + r + 4, p.y + r + 16, 'start'], [p.x - r - 4, p.y + r + 16, 'end']];
      p.label = opts[0];
      for (var k = 0; k < opts.length; k++) {
        var o = opts[k], x0 = o[2] === 'middle' ? o[0] - tw / 2 : (o[2] === 'start' ? o[0] : o[0] - tw);
        var bx = [x0, o[1] - 15, x0 + tw, o[1] + 3];
        if (bx[0] < 2 || bx[2] > w - 2 || bx[1] < 2 || bx[3] > horizon) continue;
        if (!placed.some(function (q) { return bx[0] < q[2] + 4 && bx[2] > q[0] - 4 && bx[1] < q[3] + 2 && bx[3] > q[1] - 2; })) {
          p.label = o; placed.push(bx); break;
        }
      }
    });
    var bright = sunAlt > -6;
    up.forEach(function (p) {
      var faint = p.name !== 'moon' && (p.alt < (MIN_ALT[p.name] || 10) || (bright && p.mag > -1));
      var g = el('g', { 'class': 'sv-planet planet-' + p.name + (faint ? ' is-faint' : '') });
      if (p.name === focus) g.appendChild(el('circle', { 'class': 'sv-focus', cx: p.x.toFixed(1), cy: p.y.toFixed(1), r: 22 }));
      if (p.name === 'moon') g.appendChild(moonShape(p.x, p.y, 12, D.bodies.moon.illumination, D.bodies.moon.waxing));
      else {
        g.appendChild(el('circle', { 'class': 'halo', cx: p.x.toFixed(1), cy: p.y.toFixed(1), r: 14 }));
        g.appendChild(el('circle', { cx: p.x.toFixed(1), cy: p.y.toFixed(1), r: 6.5 }));
      }
      g.appendChild(el('text', { x: p.label[0].toFixed(1), y: p.label[1].toFixed(1), 'text-anchor': p.label[2] }, L.planet[p.name]));
      svg.appendChild(g);
    });

    // the ground in front of you
    var d = 'M0 ' + h + ' ';
    for (var x = 0; x <= w; x += 3) {
      var az = facing + (x - w / 2) / (w / 2) * (fov / 2);
      var gy = horizon - ground(az) / 90 * (horizon - top);
      d += 'L' + x + ' ' + gy.toFixed(1) + ' ';
    }
    svg.appendChild(el('path', { 'class': 'sv-ground', d: d + 'L' + w + ' ' + h + ' Z' }));
    // compass marks along the bottom
    for (var c = 0; c < 360; c += 45) {
      if (!inView(c, -2)) continue;
      svg.appendChild(el('text', { 'class': 'sv-dir' + (c === facing ? ' is-facing' : ''), x: X(c).toFixed(1), y: h - 10, 'text-anchor': 'middle' }, L.dir[DIRS[c / 45]]));
    }
    stage.replaceChildren(svg);

    if (facingEl) facingEl.textContent = L.text.facing.replace('{dir}', L.dirLong[DIRS[facing / 45]]);

    // the sentence: what is up right now, wherever it is
    var all = bodiesAt(i);
    var items = all.map(function (p) {
      return L.text.item.replace('{name}', L.planet[p.name]).replace('{dir}', L.dirLong[dirOf(p.az)]).replace('{height}', height(p.alt))
        + (p.name !== 'moon' && p.alt < (MIN_ALT[p.name] || 10) ? ' ' + L.text.faint : '');
    });
    var time = D.times[i], sentence;
    if (!items.length) sentence = L.text.none.replace('{time}', time);
    else sentence = L.text.at.replace('{time}', time) + ' ' + (items.length > 1
      ? items.slice(0, -1).join(', ') + ' ' + L.text.and + ' ' + items[items.length - 1] : items[0]) + '.';
    if (sunAlt > -6) sentence += ' ' + (sunAlt > -0.5 ? L.text.day : L.text.twilight);
    nowEl.textContent = sentence;
    out.textContent = time;
    range.setAttribute('aria-valuetext', sentence);
    planners.forEach(function (svg) {
      var cursor = svg.querySelector('.pl-cursor');
      var x0 = +cursor.dataset.x0, x1 = +cursor.dataset.x1;
      var cx = x0 + (D.times.length > 1 ? i / (D.times.length - 1) : 0) * (x1 - x0);
      cursor.setAttribute('x1', cx.toFixed(1)); cursor.setAttribute('x2', cx.toFixed(1));
    });
  }

  function setIndex(v) { index = Math.max(0, Math.min(D.times.length - 1, v)); range.value = String(index); draw(); }
  function stop() {
    if (!timer) return;
    clearInterval(timer); timer = null;
    play.textContent = L.text.play; play.setAttribute('aria-pressed', 'false');
  }

  range.addEventListener('input', function () { stop(); focus = null; setIndex(+range.value); });

  // "show in the sky" on the cards: jump to the planet's best moment and face it
  document.querySelectorAll('[data-show]').forEach(function (b) {
    var name = b.dataset.show, i = D.best && D.best[name];
    if (i === undefined || !D.bodies[name]) return;
    b.hidden = false;
    b.addEventListener('click', function () {
      stop(); manual = true; focus = name;
      facing = (Math.round(D.bodies[name].az[i] / 45) * 45) % 360;
      setIndex(i);
      var r = view.getBoundingClientRect();
      if (r.top < 0 || r.bottom > window.innerHeight) {
        view.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'center' });
      }
    });
  });
  play.addEventListener('click', function () {
    if (timer) { stop(); return; }
    if (index >= D.times.length - 1) setIndex(0);
    play.textContent = L.text.pause; play.setAttribute('aria-pressed', 'true');
    timer = setInterval(function () { if (index >= D.times.length - 1) { stop(); return; } setIndex(index + 1); }, 160);
  });
  view.querySelectorAll('[data-turn]').forEach(function (b) {
    b.addEventListener('click', function () { manual = true; focus = null; facing = (facing + (+b.dataset.turn) + 360) % 360; draw(); });
  });
  planners.forEach(function (planner) {
    var cursor = planner.querySelector('.pl-cursor');
    var dragging = false;
    var fromPointer = function (ev) {
      var r = planner.getBoundingClientRect();
      var vw = +cursor.dataset.w, x0 = +cursor.dataset.x0, x1 = +cursor.dataset.x1;
      var vx = (ev.clientX - r.left) / r.width * vw;
      stop(); focus = null; setIndex(Math.round((vx - x0) / (x1 - x0) * (D.times.length - 1)));
    };
    planner.addEventListener('pointerdown', function (ev) { dragging = true; planner.setPointerCapture(ev.pointerId); fromPointer(ev); });
    planner.addEventListener('pointermove', function (ev) { if (dragging) fromPointer(ev); });
    planner.addEventListener('pointerup', function () { dragging = false; });
    planner.classList.add('is-interactive');
  });
  var lastW = 0, pending = false;
  window.addEventListener('resize', function () {
    if (pending) return;
    pending = true;
    requestAnimationFrame(function () {
      pending = false;
      if (Math.abs(stage.clientWidth - lastW) > 2) { lastW = stage.clientWidth; draw(); }
    });
  });

  play.textContent = L.text.play;
  view.hidden = false;
  controls.hidden = false;
  lastW = stage.clientWidth;
  draw();
})();
