var dupSelectRadio = function(name, value) {
  var form = document.MergeForm;
  var elements = form.elements;
  for (var i in elements) {
    if (elements[i].type == 'radio' && 
      elements[i].name == name) {
      if (elements[i].value == value) {
        elements[i].checked = true;
      } else {
        elements[i].checked = false;
      }
    } 
  } 
};


var dupChangeCssClass = function(id, type) {
  var e = document.getElementById(id);
  if (!e) {
    alert("Could not find element with id " + id);
    return false;
  }

  if (type == 'master' && e.className.indexOf('dupMaster') < 0) { 
    e.className = e.className.replace(' dupCopy', '').replace(' dupNone', '') + ' dupMaster';
  } else if (type == 'copy' && e.className.indexOf('dupCopy') < 0) {
    e.className = e.className.replace(' dupMaster', '').replace(' dupNone', '') + ' dupCopy';
  } else {
    e.className = e.className.replace(' dupMaster', '').replace(' dupCopy', '') + ' dupNone';
  }
}

var dupSelectDuplicate = function(dupIndex, mediaId) {
  var e = null;
  var form = document.MergeForm;
  if (!form) {
    alert("Formular MergeForm could not be found");
    return true;
  }
  // prefix of duplicate group
  var prefix = 'data[' + dupIndex + ']';
  // active radio buttons of the duplicates
  var duplicates = [];
  // the current selected radio button
  var selected = null;

  // fetch active radio buttons and the current element
  var elements = form.elements;
  for (var i in elements) {
    if (elements[i].type == 'radio' && 
      elements[i].name.substr(0, prefix.length) == prefix &&
      elements[i].checked == true) {
      duplicates.push(elements[i]);
      if (elements[i].name == prefix + '[' + mediaId + ']') {
        selected = elements[i];
      }
    }
  }

  if (selected) {
    // change the old master to a copy
    if (selected.value == 'master') {
      for (var i in duplicates) {
        if (duplicates[i] != selected &&
          duplicates[i].value == 'master') {
          dupSelectRadio(duplicates[i].name, 'copy');
          var matches = duplicates[i].name.match(/[0-9]+\]$/);
          if (matches.length == 1) {
            var id = matches[0].substr(0, matches[0].length - 1);
            dupChangeCssClass('media-' + id, 'copy');
          }
        }
      }
      dupChangeCssClass('media-' + mediaId, 'master');
    } else if (selected.value == 'copy') {
      dupChangeCssClass('media-' + mediaId, 'copy');
    } else {
      dupChangeCssClass('media-' + mediaId, 'none');
    }
  }
};
