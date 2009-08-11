<?php
/*
 * phtagr.
 * 
 * Multi-user image gallery.
 * 
 * Copyright (C) 2006-2009 Sebastian Felis, sebastian@phtagr.org
 * 
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; version 2 of the 
 * License.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */

class DuplicateHelper extends AppHelper {
  var $helpers = array('time', 'ajax', 'html', 'form', 'query');

 function _radio($index, $mediaId, $value, $checked = false) {
    $output = '<input type="radio" name="data['.$index.']['.$mediaId.']" ';
    $output .= 'id="dup.'.$index.'.'.$mediaId.'" ';
    $output .= 'value="'.$value.'" ';
    if ($checked) {
      $output .= 'checked="checked" ';
    }
    $output .= 'onchange="dupSelectDuplicate('.$index.','.$mediaId.');" '; 
    $output .= '/>';
    return $output;
  }

  function selectType($index, $media) {
    $output = '';
    $output .= $this->_radio($index, $media['Media']['id'], 'master', $media['dupMaster']).' Master';
    $output .= $this->_radio($index, $media['Media']['id'], 'copy', !$media['dupMaster']).' Copy';
    $output .= $this->_radio($index, $media['Media']['id'], 'none', false).' None';
    return $output;
  }
}
?>
