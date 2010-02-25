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

class DuplicateBehavior extends ModelBehavior 
{
  var $config = array();

  function setup(&$model, $config = array()) {
    $this->config[$model->name] = $config;
  }

  /** Find duplicates regarding a specific method. 
    @param model Reference of current model
    @param method Duplicate finder method. Currently only 'date' is implemented
    @param params Query parameters like 'conditions', 'limit' and 'fields'
    @return Array of duplicates */
  function findDup(&$model, $method = 'date', $params = array()) {
    $params = am(array('conditions' => array(), 'limit' => 12, 'fields' => array()), $params);
    extract($params);

    if (!in_array("{$model->alias}.id", $fields)) {
      $fields[] = "{$model->alias}.id";
    }
    $keyName = false;
    $keyField = false;
    switch ($method) {
      case 'date':
        $keyField = 'date';
        $keyName = "{$model->alias}.$keyField";
        if (!in_array($keyName, $fields)) {
          $fields[] = $keyName;
        }
        break;
      default:
        Logger::err("Unknown method $method");
        return false;
    }

    $group = '';
    switch ($method) {
      case 'date':
        $group .= "$keyName HAVING COUNT({$model->alias}.id) > 1";
        break;
    }

    // Fetch duplicates
    $result = $model->find('all', compact('fields', 'conditions', 'group', 'limit'));
    // Get all duplicates with full model data
    $duplicates = Set::extract($result, "{n}.{$model->alias}.$keyField");
    $result = array();
    foreach ($duplicates as $key) {
      $result[] = $model->find('all', array('conditions' => array($keyName => $key)));
    }
    return $result;
  }

  /** Finds a duplicate master regarding a specific method
    @param model Reference of current model
    @param data Duplicate data (retrieved from findDup())
    @param method Master method. Currently only 'height'
    @return Data with marked master. */
  function getMaster(&$model, &$data, $method = 'height') {
    switch ($method) {
      case 'views':
        $field = 'clicks';
        break;
      case 'height':
        $field = "height";
        break;
      default:
        Logger::err("Unknown method $method");
        return false;
    }

    foreach ($data as $dupIndex => $duplicates) {
      $masterIndex = false;
      $masterValue = false;

      $fileMasterIndex = false;
      $fileMasterValue = false;

      // find master
      foreach ($duplicates as $index => $item) {
        // extract returns an array
        list($value) = Set::extract("/{$model->alias}/$field", $item);
        if ($masterIndex === false || $masterValue < $value) {
          $masterIndex = $index;
          $masterValue = $value;
        }
        
        $size = array_sum(Set::extract("/File/size", $item));
        if ($fileMasterIndex === false || $fileMasterValue < $size) {
          $fileMasterIndex = $index;
          $fileMasterValue = $size;
        }     
      }
      // set master and their copies
      foreach ($duplicates as $index => $item) {
        if ($masterIndex === $index) {
          $data[$dupIndex][$index]['dupMaster'] = true;
        } else {
          $data[$dupIndex][$index]['dupMaster'] = false;
        }

        if ($fileMasterIndex === $index) {
          $data[$dupIndex][$index]['fileMaster'] = true;
        } else {
          $data[$dupIndex][$index]['fileMaster'] = false;
        }
      }
    }
    return $data;
  }

  /** Merge to models together
    @param model Reference to current model
    @param src Model data of source
    @param dst Model data of destination */
  function merge(&$model, $src, $dst) {
    $dstModel = $this->_mergeData($src[$model->alias], $dst[$model->alias], array(
      'caption' => 'overwriteEmpty',
      'clicks' => 'add',
      'lastview' => 'max',
      'latitude' => 'overwriteEmpty',
      'longitude' => 'overwriteEmpty',
      'ranking' => 'max', 
      'voting' => 'avg',
      'votes' => 'add'
      ));
    if (!$model->save($dstModel)) {
      Logger::err("Could not merge {$model->alias} data from {$src[$model->alias]['id']} to {$dst[$model->alias]['id']}");
    } else {
      Logger::debug("Merged {$model->alias} data from {$src[$model->alias]['id']} to {$dst[$model->alias]['id']}");
    }

    $this->_mergeAssociations($model, $src, $dst, array('File', 'Location'));
    $this->_mergeLocations($model, $src, $dst);
    $model->setFlag($dst, MEDIA_FLAG_DIRTY);
    return true;
  }

  /** Merge model data
    @param src Model data of source
    @param dst Model data of destination
    @param rules Array of rules
    @return Merged model data */
  function _mergeData($src, $dst, $rules) {
    foreach ($src as $field => $value) {
      if (!isset($rules[$field])) {
        continue;
      }

      switch ($rules[$field]) {
        case 'sub':
          $dst[$field] -= $src[$field];
          break;
        case 'add':
          $dst[$field] += $src[$field];
          break;
        case 'min':
          $dst[$field] = min($src[$field], $dst[$field]);
          break;
        case 'max':
          $dst[$field] = max($src[$field], $dst[$field]);
          break;
        case 'avg':
          $dst[$field] = ($src[$field] + $dst[$field]) / 2;
          break;
        case 'overwrite':
          $dst[$field] = $src[$field];
          break;
        case 'overwriteEmpty':
          if (empty($dst[$field])) {
            $dst[$field] = $src[$field];
          }
          break;
        default:
          Logger::err("Unknown merge rule {$rule[$field]}");
      }
    }
    return $dst;
  }

  /** Merges the location from the source to the destination media. The
    specific type of location of the destination has priority 
    @param model Reference to model object
    @param src Media model data of the source
    @param dst Media model data of the destination */
  function _mergeLocations(&$model, $src, $dst) {
    $srcLocations = array();
    $dstLocations = array();
    if (count($src['Location'])) {
      $srcLocations = Set::combine($src, 'Location.{n}.type', 'Location.{n}.id');
    }
    if (count($dst['Location'])) {
      $dstLocations = Set::combine($dst, 'Location.{n}.type', 'Location.{n}.id');
    }

    // merge
    $new = false;
    foreach ($srcLocations as $type => $id) {
      if (!isset($dstLocations[$type])) {
        $dstLocations[$type] = $id;
        $new = true;
      }
    }

    // save
    if ($new) {
      $dummy['Media']['id'] = $dst['Media']['id'];
      $dummy['Location']['Location'] = array_values($dstLocations);
      if (!$model->save($dummy)) {
        Logger::err("Could not merge location of {$src['Media']['id']} to {$dst['Media']['id']}");
        return false;
      } else {
        Logger::debug("Merged location of {$src['Media']['id']} to {$dst['Media']['id']}");
      }
    }
    return true;
  }

  /** Merges the associations of the current model
    @param model Reference to current model object
    @param src Model data of source
    @param dst Model data of destionation
    @param skipAccosiation Array of association which should be skip */
  function _mergeAssociations(&$model, $src, $dst, $skipAssociations = array()) {
    foreach ($model->hasMany as $association => $config) {
      if (in_array($association, $skipAssociations)) {
        Logger::debug("Skip association $association");
        continue;
      }
      //Logger::debug($config);
      $conditions = array("$association.{$config['foreignKey']}" => $src[$model->alias]['id']);
      $fields = array("$association.{$config['foreignKey']}" => $dst[$model->alias]['id']);
      if (!$model->{$association}->updateAll($fields, $conditions)) {
        Logger::err("Could not move $association of {$media->alias} {$src[$model->alias]['id']} to {$media->alias} {$dst[$model->alias]['id']}");
      } else {
        Logger::debug("Moved $association of {$model->alias} {$src[$model->alias]['id']} to {$model->alias} {$dst[$model->alias]['id']}");
      }
    }

    foreach ($model->hasAndBelongsToMany as $association => $config) {
      if (in_array($association, $skipAssociations)) {
        Logger::debug("Skip association $association");
        continue;
      }
      $srcIds = Set::extract("/$association/id", $src);
      $dstIds = Set::extract("/$association/id", $dst);
      $addIds = array_diff($srcIds, $dstIds);
      if (count($addIds)) {
        $dummy[$model->alias]['id'] = $dst[$model->alias]['id'];
        $dummy[$association][$association] = array_unique(am($addIds, $dstIds));
        if (!$model->save($dummy)) {
          Logger::err("Could not update {$model->alias}.$association(".implode(', ', $addIds).") to {$dst[$model->alias]['id']}");
        } else {
          Logger::debug("Add $association(".implode(', ', $addIds).") to {$model->alias} {$dst[$model->alias]['id']}");
        }
      }
    }
    return true;
  }
}
?>