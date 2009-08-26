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

class DupfinderController extends DupfinderAppController
{
  var $name = 'Dupfinder';
  var $uses = array('Media');
  var $helpers = array('imageData', 'duplicate', 'number');

  function beforeFilter() {
    if (!$this->hasRole(ROLE_USER)) {
      Logger::verbose("Deny plugin for non users");
      $this->redirect('/');
    }
    $this->Media->Behaviors->attach('dupfinder.duplicate');
  }

  function index() {
    if ($this->Session->check('dupFinder.index.data')) {
      $this->data = $this->Session->read('dupFinder.index.data');
    }
  }

  function find() {
    if (empty($this->data) && $this->Session->check('dupFinder.find.query')) {
      $query = $this->Session->read('dupFinder.find.query');
    } else {
      $this->Session->write('dupFinder.index.data', $this->data);
      $query = array('limit' => 12);
      $conditions = array("User.id = ".$this->getUserId());
      if (!empty($this->data)) {
        if (!empty($this->data['Media']['from'])) {
          $conditions[] = "Media.date >= '".date('Y-m-d H:m:s', strtotime($this->data['Media']['from']))."'";
        }    
        if (!empty($this->data['Media']['to'])) {
          $conditions[] = "Media.date <= '".date('Y-m-d H:m:s', strtotime($this->data['Media']['to']))."'";
        }    
        if (!empty($this->data['Media']['show'])) {
          $query['limit'] = min(240, max(3, intval($this->data['Media']['show'])));
        }    
      } 
      $query['conditions'] = $conditions;
      $this->Session->write('dupFinder.find.query', $query);
    }
    $this->data = $this->Media->findDup('date', $query);
    if (count($this->data) == 0) {
      $this->render('noduplicates');
    } else {
      $this->Media->getMaster(&$this->data);
    }
  }

  function merge() {
    $data['master'] = 0;
    $data['copies'] = 0;
    if (!empty($this->data)) {
      foreach($this->data as $dupIndex => $duplicates) {
        // select master and copies
        $masters = array();
        $copies = array(); 
        foreach ($duplicates as $id => $type) {
          if ($type == 'master') {
            $masters[] = $id;
          } elseif ($type == 'copy') {
            $copies[] = $id;
          }
        }
        // check master and copies
        if (count($masters) == 0) {
          Logger::verbose("No master selected for duplicates set $dupIndex");
          Logger::debug($duplicates);
          continue;
        } elseif (count($masters) > 1) {
          Logger::verbose("Deny multiple selected masters for duplicates set $dupIndex");
          Logger::debug($duplicates);
          continue;
        } elseif (count($copies) < 0) {
          Logger::verbose("No copy selected for duplicates set $dupIndex");
          Logger::debug($duplicates);
          continue;
        }

        $masterId = array_pop($masters);
        $master = $this->Media->findById($masterId);
        if (!$master) {
          Logger::verbose("Could not find master with id $masterId");
          continue;
        }
        foreach ($copies as $copyId) {
          $copy = $this->Media->findById($copyId);
          if (!$copy) {
            Logger::verbose("Could not find copy with id $copyId");
            continue;
          }
          Logger::verbose("Merge media $copyId to $masterId and delete media $copyId");
          $this->Media->merge($copy, $master);
          $this->Media->delete($copy['Media']['id']);
          $data['copies']++;
        }
        $data['master']++;
      }
    }
    $this->data = $data;
  }
}
?>
