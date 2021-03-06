<h1>Duplicate Finder</h1>

<p>Welcome to the Duplicate Finder plugin. This plugin finds duplicated media
according the date and merges the media data from multiple copies to one master
media. After merging the data the copies are deleted while the media files are
kept.</p>

<p>Please select your query</p>

<?php echo $this->Form->create(null, array('action' => 'find')); ?>
<fieldset><legend>Range of date</legend>
  <?php echo $this->Form->input('Media.from', array('label' => 'From date')); ?>
  <?php echo $this->Form->input('Media.to', array('label' => 'To date')); ?>
</fieldset>

<fieldset><legend>Other</legend>
  <?php echo $this->Form->input('Media.show', array('label' => 'Page size')); ?>
</fieldset>
<?php echo $this->Form->end("Find Duplicates"); ?>
