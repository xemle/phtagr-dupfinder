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

  var $helpers = array('form');

  function selectDuplicate($index, $media) {
    $mediaId = $media['Media']['id'];
    $output = '';
    $output .= $this->form->radio(
      "Set$index.Dup.$mediaId",
      array('master' => 'Master', 'copy' => 'Copy', 'none' => 'None'),
      array(
        'onchange' => "dupSelectDuplicate('$index', '$mediaId');",
        'legend' => false,
        'label' => false,
        'default' => $media['dupMaster'] ? 'master' : 'copy'
        )
      );
    return $output;
  }

  function selectFile($index, $media) {
    $mediaId = $media['Media']['id'];
    $output = '';
    $output .= $this->form->radio(
      "Set$index.File.$mediaId",
      array('master' => 'File Master', 'copy' => 'File Copy'),
      array(
        'onchange' => "dupSelectFile('$index', '$mediaId');",
        'legend' => false,
        'label' => false,
        'default' => $media['fileMaster'] ? 'master' : 'copy'
        )
      );
    return $output;
  }

}
?>