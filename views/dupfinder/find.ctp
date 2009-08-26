<?php echo $html->css('/dupfinder/css/dupfinder'); ?>
<?php echo $javascript->link('/dupfinder/js/dupfinder'); ?>
<h1>Duplicates Results</h1>

<?php echo $form->create(null, array('action' => 'merge', 'name' => 'MergeForm')); ?>

<?php foreach ($this->data as $dupIndex => $duplicates): ?>

<h2>Duplicate Set <?php echo ($dupIndex+1); ?></h2>

<?php $cell = 0; ?>
<?php foreach ($duplicates as $media): ?>
<?php $side = $cell % 2 ? 'r' : 'l'; ?>
<?php if (!($cell % 2)): ?><div class="subcolumns"><?php endif; ?>
<div class="c50<?=$side; ?>"><div class="subc<?=$side; ?> thumb <?php if ($media['dupMaster']) { echo "dupMaster"; } else { echo "dupCopy"; } ?>" id="media-<?= $media['Media']['id'];?>" >

<h2><?php echo $media['Media']['name']; ?></h2>

<div class="image">
<?php $size = $imageData->getimagesize($media, OUTPUT_SIZE_THUMB); ?>
<a href="/images/view/<?php echo $media['Media']['id']; ?>">
<img src="/media/thumb/<?php echo $media['Media']['id']; ?>" <?php echo $size[3]; ?> alt="<?php echo $media['Media']['name']; ?>" />
</a>
</div> <!-- image -->

<div class="user">
  <?php echo $duplicate->selectType($dupIndex, $media)."\n"; ?>
</div>

<div class="meta">
<div id="<?php echo 'meta-'.$media['Media']['id']; ?>">
<table>
  <?php 
  $size = $imageData->getimagesize($media);
  $files = array();
  foreach ($media['File'] as $file) {
    $files[] = $file['file'].' ('.$number->toReadableSize($file['size']).')';
  }
  echo $html->tableCells(array(
  array('Date:', $media['Media']['date']),
  array('Size:', $size[0].'x'.$size[1]),
  array('Files:', implode(', ',$files)),
  array('Clicks:', $media['Media']['clicks']),
  array('Comments:', count($media['Comment'])),
  array('Tags:', implode(', ', Set::extract($media, '/Tag/name'))),
  array('Categories:', implode(', ', Set::extract($media, '/Category/name'))),
  array('Locations:', implode(', ', Set::extract($media, '/Location/name')))
  )); ?>
</table>
</div>
</div>

</div><!-- c50[lr] --></div><!-- subc[lr] -->
<?php if ($side == 'r'): ?></div><!-- subcolumns --><?php endif; ?>
<?php $cell++; endforeach; // single duplicate ?>
<?php /* fix for odd number */ if ($cell % 2): ?>
</div><!-- subcolumns -->
<?php endif; ?>

<?php endforeach; // all duplicates ?>
<?php echo $form->end("Merge Media"); ?>
