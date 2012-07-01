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
App::uses('DupfinderAppController', 'Dupfinder.Controller');

class DupfinderController extends DupfinderAppController {
  var $name = 'Dupfinder';
  var $uses = array('Media', 'MyFile');
  var $helpers = array('ImageData', 'Dupfinder.Duplicate', 'Number');

  function beforeFilter() {
    if (!$this->hasRole(ROLE_USER)) {
      Logger::verbose("Deny plugin for non users");
      $this->redirect('/');
    }
    $this->Media->Behaviors->attach('Dupfinder.Duplicate');
  }

  function index() {
    if ($this->Session->check('dupFinder.index.data')) {
      $this->request->data = $this->Session->read('dupFinder.index.data');
    }
  }

  function find() {
    if (empty($this->request->data) && $this->Session->check('dupFinder.find.query')) {
      $query = $this->Session->read('dupFinder.find.query');
    } else {
      $this->Session->write('dupFinder.index.data', $this->request->data);
      $query = array('limit' => 12);
      $conditions = array("User.id = ".$this->getUserId());
      if (!empty($this->request->data)) {
        if (!empty($this->request->data['Media']['from'])) {
          $conditions[] = "Media.date >= '".date('Y-m-d H:m:s', strtotime($this->request->data['Media']['from']))."'";
        }    
        if (!empty($this->request->data['Media']['to'])) {
          $conditions[] = "Media.date <= '".date('Y-m-d H:m:s', strtotime($this->request->data['Media']['to']))."'";
        }    
        if (!empty($this->request->data['Media']['show'])) {
          $query['limit'] = min(240, max(3, intval($this->request->data['Media']['show'])));
        }    
      } 
      $query['conditions'] = $conditions;
      $this->Session->write('dupFinder.find.query', $query);
    }
    $this->request->data = $this->Media->findDup('date', $query);
    if (count($this->request->data) == 0) {
      $this->render('noduplicates');
    } else {
      $this->Media->getMaster(&$this->request->data, 'views');
    }
  }

  /** Extract master and copies of the submitted form and validate the input
   @param data Duplicate data set
   @return array of master Id and copy Ids */
  function _findMasterAndCopies($data) {
    $masters = array();
    $copies = array(); 
    foreach ($data as $id => $type) {
      if ($type == 'master') {
        $masters[] = $id;
      } elseif ($type == 'copy') {
        $copies[] = $id;
      }
    }
 
    // check master and copies
    if (count($masters) == 0) {
      Logger::verbose("No master selected for duplicates set");
      Logger::debug($data);
      return array(false, false);
    } elseif (count($masters) > 1) {
      Logger::verbose("Deny multiple selected masters for duplicates set");
      Logger::debug($data);
      return array(false, false);
    } elseif (count($copies) < 1) {
      Logger::verbose("No copy selected for duplicates set");
      Logger::debug($data);
      return array(false, false);
    }
    $master = array_pop($masters);
    return array($master, $copies);
  }

  function merge() {
    $data['master'] = 0;
    $data['copies'] = 0;
    if (!empty($this->request->data)) {
      foreach($this->request->data as $dupIndex => $duplicates) {
        // select master and copies of a duplicate set
        list($masterId, $copies) = $this->_findMasterAndCopies($duplicates['Dup']);
        list($fileMasterId, $fileCopies) = $this->_findMasterAndCopies($duplicates['File']);

        if (!$masterId || !$fileMasterId) {
          Logger::err("No masterId or file masterId found for duplicate set $dupIndex");
          continue;
        }

        $master = $this->Media->findById($masterId);
        if (!$master) {
          Logger::verbose("Could not find master with id $masterId");
          continue;
        }

        // Merge meta data from copies to master
        foreach ($copies as $copyId) {
          $copy = $this->Media->findById($copyId);
          if (!$copy) {
            Logger::verbose("Could not find copy with id $copyId");
            continue;
          }
          Logger::verbose("Merge media $copyId to $masterId");
          $this->Media->merge($copy, $master);

          $data['copies']++;
        }

        // Apply file master to media master
        if ($fileMasterId != $masterId) {
          $fileMaster = $this->Media->findById($fileMasterId);
          if (!$fileMaster) {
            Logger::verbose("Could not find file master with id $fileMasterId");
          } else {
            // Use name of file master name if name is equal to one of media's file name
            if (in_array($master['Media']['name'], Set::extract('/File/file', $master))) {
              $this->Media->id = $masterId;
              $this->Media->saveField('name', $fileMaster['Media']['name']);
              Logger::verbose("Rename media master from {$master['Media']['name']} to {$fileMaster['Media']['name']}");
            }

            Logger::verbose("Unlink files of media master $masterId");
            $this->MyFile->unlinkMedia($masterId);
            Logger::verbose("Set files " . implode(', ', Set::extract('/File/id', $fileMaster)) . " of file master $fileMasterId to master media $masterId");
            foreach ($fileMaster['File'] as $file) {
              $this->MyFile->setMedia($file, $masterId);              
            }

            // Overwrite media properties from file master to media master
            $fieldList = array('width', 'height', 'flag', 'type', 'orientation', 'duration', 'aperture', 'shutter', 'iso', 'model');
            $dummy = array('Media' => array('id' => $master['Media']['id']));
            foreach ($fieldList as $field) {
              $dummy['Media'][$field] = $fileMaster['Media'][$field];
            }
            if (!$this->Media->save($dummy, true, $fieldList)) {
              Logger::err("Clould not save model data of {$master['Media']['id']}");
            } else {
              Logger::verbose("Overwrite media properties from file master {$fileMaster['Media']['id']} to media master {$master['Media']['id']}: ". implode(', ', $fieldList));
            }
             
            $this->Media->deleteCache($master);
          }
        }

        // Delete Media copies
        Logger::verbose("Delete media copies " . implode(', ', $copies));
        foreach ($copies as $copyId) {
          $this->Media->delete($copyId);
        }
        $data['master']++;
      }
    }
    $this->request->data = $data;
  }
}
?>
