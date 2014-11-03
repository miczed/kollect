// easing functions http://goo.gl/5HLl8
Math.easeInOutQuad = function (t, b, c, d) {
  t /= d/2;
  if (t < 1) {
    return c/2*t*t + b
  }
  t--;
  return -c/2 * (t*(t-2) - 1) + b;
};
 
Math.easeInCubic = function(t, b, c, d) {
  var tc = (t/=d)*t*t;
  return b+c*(tc);
};
 
Math.inOutQuintic = function(t, b, c, d) {
  var ts = (t/=d)*t,
  tc = ts*t;
  return b+c*(6*tc*ts + -15*ts*ts + 10*tc);
};
 
// requestAnimationFrame for Smart Animating http://goo.gl/sx5sts
var requestAnimFrame = (function(){
  return  window.requestAnimationFrame || window.webkitRequestAnimationFrame || window.mozRequestAnimationFrame || function( callback ){ window.setTimeout(callback, 1000 / 60); };
})();


app.service('smoothScroll', function(){
    this.scrollTo = function(to, callback, duration) {
		  // figure out if this is moz || IE because they use documentElement
		  var doc = (navigator.userAgent.indexOf('Firefox') != -1 || navigator.userAgent.indexOf('MSIE') != -1) ? document.documentElement : document.body,
		  start = doc.scrollTop,
		  change = to - start,
		  currentTime = 0,
		  increment = 20;
		  duration = (typeof(duration) === 'undefined') ? 500: duration;
		  var animateScroll = function(){
		    // increment the time
		    currentTime += increment;
		    // find the value with the quadratic in-out easing function
		    var val = Math.easeInOutQuad(currentTime, start, change, duration);
		    // move the document.body
		    doc.scrollTop = val;
		    // do the animation unless its over
		    if(currentTime < duration) {
		      requestAnimFrame(animateScroll);
		    } else {
		      if (callback && typeof(callback) === 'function') {
		        // the animation is done so lets callback
		        callback();
		      }
		    }
		  };
		  animateScroll();
		}    
});