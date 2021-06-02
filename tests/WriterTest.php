<?php

namespace Tests;

use Carbon\Carbon;
use Illuminate\Filesystem\Filesystem;
use Onlime\LaravelSqlReporter\Config;
use Onlime\LaravelSqlReporter\FileName;
use Onlime\LaravelSqlReporter\Formatter;
use Onlime\LaravelSqlReporter\SqlQuery;
use Onlime\LaravelSqlReporter\Writer;
use Mockery;

class WriterTest extends UnitTestCase
{
    /**
     * @var Formatter|\Mockery\Mock
     */
    private $formatter;

    /**
     * @var Config|\Mockery\Mock
     */
    private $config;

    /**
     * @var FileName|\Mockery\Mock
     */
    private $filename;

    /**
     * @var Writer
     */
    private $writer;

    /**
     * @var string
     */
    private $directory;

    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @var Carbon
     */
    private $now;

    protected function setUp(): void
    {
        $this->now = Carbon::parse('2015-02-03 06:41:31');
        Carbon::setTestNow($this->now);
        $this->formatter = Mockery::mock(Formatter::class);
        $this->config = Mockery::mock(Config::class);
        $this->filename = Mockery::mock(FileName::class);
        $this->writer = new Writer($this->formatter, $this->config, $this->filename);
        $this->directory = __DIR__ . '/test-dir/directory';
        $this->filesystem = new Filesystem();
    }

    protected function tearDown(): void
    {
        $this->filesystem->deleteDirectory($this->directory);
        parent::tearDown();
    }

    /** @test */
    public function it_creates_directory_if_it_does_not_exist_for_1st_query()
    {
        $query = new SqlQuery(1, 'test', [], 5.41);
        $this->config->shouldReceive('queriesEnabled')->once()->withNoArgs()->andReturn(false);
        $this->config->shouldReceive('logDirectory')->once()->withNoArgs()->andReturn($this->directory);
        $this->assertFileDoesNotExist($this->directory);
        $this->writer->save($query);
        $this->assertFileExists($this->directory);
        $this->assertEmpty($this->filesystem->allFiles($this->directory));
    }

    /** @test */
    public function it_does_not_create_directory_if_it_does_not_exist_for_2nd_query()
    {
        $query = new SqlQuery(2, 'test', [], 5.41);
        $this->config->shouldReceive('queriesEnabled')->once()->withNoArgs()->andReturn(false);
        $this->config->shouldNotReceive('logDirectory');
        $this->assertFileDoesNotExist($this->directory);
        $this->writer->save($query);
        $this->assertFileDoesNotExist($this->directory);
    }

    /** @test */
    public function it_creates_log_file()
    {
        $lineContent = 'Sample log line';
        $expectedContent = "-- header\nSample log line\n";
        $expectedFileName = $this->now->format('Y-m') . '-log.sql';

        $query = new SqlQuery(1, 'test', [], 5.41);
        $this->formatter->shouldReceive('getLine')->once()->with($query)->andReturn($lineContent);
        $this->formatter->shouldReceive('getHeader')->once()->withNoArgs()->andReturn('-- header');
        $this->config->shouldReceive('queriesEnabled')->once()->withNoArgs()->andReturn(true);
        $this->config->shouldReceive('queriesPattern')->once()->withNoArgs()->andReturn('#.*#i');
        $this->config->shouldReceive('logDirectory')->times(3)->withNoArgs()->andReturn($this->directory);
        $this->config->shouldReceive('queriesOverrideLog')->once()->withNoArgs()->andReturn(false);
        $this->config->shouldReceive('queriesMinExecTime')->once()->withNoArgs()->andReturn(0);
        $this->filename->shouldReceive('getLogfile')->times(2)->withNoArgs()->andReturn($expectedFileName);
        $this->writer->save($query);
        $this->assertFileExists($this->directory);
        $this->assertCount(1, $this->filesystem->allFiles($this->directory));
        $this->assertFileExists($this->directory . '/' . $expectedFileName);
        $this->assertSame($expectedContent, file_get_contents($this->directory . '/' . $expectedFileName));
    }

    /** @test */
    public function it_appends_to_existing_log_file()
    {
        $expectedFileName = $this->now->format('Y-m') . '-log.sql';
        mkdir($this->directory, 0777, true);
        $initialContent = "Initial file content\n";
        file_put_contents($this->directory . '/' . $expectedFileName, $initialContent);

        $lineContent = 'Sample log line';
        $expectedContent = $initialContent . "-- header\nSample log line\n";

        $query = new SqlQuery(1, 'test', [], 5.41);
        $this->formatter->shouldReceive('getLine')->once()->with($query)->andReturn($lineContent);
        $this->formatter->shouldReceive('getHeader')->once()->withNoArgs()->andReturn('-- header');
        $this->config->shouldReceive('queriesEnabled')->once()->withNoArgs()->andReturn(true);
        $this->config->shouldReceive('queriesPattern')->once()->withNoArgs()->andReturn('#.*#i');
        $this->config->shouldReceive('logDirectory')->times(3)->withNoArgs()->andReturn($this->directory);
        $this->config->shouldReceive('queriesOverrideLog')->once()->withNoArgs()->andReturn(false);
        $this->config->shouldReceive('queriesMinExecTime')->once()->withNoArgs()->andReturn(0);
        $this->filename->shouldReceive('getLogfile')->times(2)->withNoArgs()->andReturn($expectedFileName);
        $this->assertFileExists($this->directory . '/' . $expectedFileName);
        $this->writer->save($query);
        $this->assertCount(1, $this->filesystem->allFiles($this->directory));
        $this->assertFileExists($this->directory . '/' . $expectedFileName);
        $this->assertSame($expectedContent, file_get_contents($this->directory . '/' . $expectedFileName));
    }

    /** @test */
    public function it_replaces_current_file_content_for_1st_query_when_overriding_is_turned_on()
    {
        $expectedFileName = $this->now->format('Y-m') . '-log.sql';
        mkdir($this->directory, 0777, true);
        $initialContent = "Initial file content\n";
        file_put_contents($this->directory . '/' . $expectedFileName, $initialContent);

        $lineContent = 'Sample log line';
        $expectedContent = "-- header\nSample log line\n";

        $query = new SqlQuery(1, 'test', [], 5.41);
        $this->formatter->shouldReceive('getLine')->once()->with($query)->andReturn($lineContent);
        $this->formatter->shouldReceive('getHeader')->once()->withNoArgs()->andReturn('-- header');
        $this->config->shouldReceive('queriesEnabled')->once()->withNoArgs()->andReturn(true);
        $this->config->shouldReceive('queriesPattern')->once()->withNoArgs()->andReturn('#.*#i');
        $this->config->shouldReceive('logDirectory')->times(3)->withNoArgs()->andReturn($this->directory);
        $this->config->shouldReceive('queriesOverrideLog')->once()->withNoArgs()->andReturn(true);
        $this->config->shouldReceive('queriesMinExecTime')->once()->withNoArgs()->andReturn(0);
        $this->filename->shouldReceive('getLogfile')->times(2)->withNoArgs()->andReturn($expectedFileName);
        $this->assertFileExists($this->directory . '/' . $expectedFileName);
        $this->writer->save($query);
        $this->assertCount(1, $this->filesystem->allFiles($this->directory));
        $this->assertFileExists($this->directory . '/' . $expectedFileName);
        $this->assertSame($expectedContent, file_get_contents($this->directory . '/' . $expectedFileName));
    }

    /** @test */
    public function it_appends_to_current_file_content_for_2nd_query_when_overriding_is_turned_on()
    {
        $expectedFileName = $this->now->format('Y-m') . '-log.sql';
        mkdir($this->directory, 0777, true);
        $initialContent = "Initial file content\n";
        file_put_contents($this->directory . '/' . $expectedFileName, $initialContent);

        $lineContent = 'Sample log line';
        $expectedContent = "-- header\n$lineContent\n$lineContent\n";

        $query1 = new SqlQuery(1, 'test', [], 5.41);
        $query2 = new SqlQuery(2, 'test', [], 5.41);
        $this->formatter->shouldReceive('getLine')->twice()->andReturn($lineContent);
        $this->formatter->shouldReceive('getHeader')->once()->withNoArgs()->andReturn('-- header');
        $this->config->shouldReceive('queriesEnabled')->twice()->withNoArgs()->andReturn(true);
        $this->config->shouldReceive('queriesPattern')->twice()->withNoArgs()->andReturn('#.*#i');
        $this->config->shouldReceive('logDirectory')->times(4)->withNoArgs()->andReturn($this->directory);
        $this->config->shouldReceive('queriesOverrideLog')->once()->withNoArgs()->andReturn(true);
        $this->config->shouldReceive('queriesMinExecTime')->twice()->withNoArgs()->andReturn(0);
        $this->filename->shouldReceive('getLogfile')->times(3)->withNoArgs()->andReturn($expectedFileName);
        $this->assertFileExists($this->directory . '/' . $expectedFileName);
        $this->writer->save($query1);
        $this->writer->save($query2);
        $this->assertCount(1, $this->filesystem->allFiles($this->directory));
        $this->assertFileExists($this->directory . '/' . $expectedFileName);
        $this->assertSame($expectedContent, file_get_contents($this->directory . '/' . $expectedFileName));
    }

    /** @test */
    public function it_saves_select_query_to_file_when_pattern_set_to_select_queries()
    {
        $expectedFileName = $this->now->format('Y-m') . '-log.sql';
        $lineContent = 'Sample log line';
        $expectedContent = "\n$lineContent\n";

        $query = new SqlQuery(1, 'select * FROM test', [], 5.41);
        $this->formatter->shouldReceive('getLine')->once()->with($query)->andReturn($lineContent);
        $this->formatter->shouldReceive('getHeader')->once()->withNoArgs()->andReturn('');
        $this->config->shouldReceive('queriesEnabled')->once()->withNoArgs()->andReturn(true);
        $this->config->shouldReceive('queriesPattern')->once()->withNoArgs()->andReturn('#^SELECT .*$#i');
        $this->config->shouldReceive('logDirectory')->times(3)->withNoArgs()->andReturn($this->directory);
        $this->config->shouldReceive('queriesMinExecTime')->once()->withNoArgs()->andReturn(0);
        $this->filename->shouldReceive('getLogfile')->twice()->withNoArgs()->andReturn($expectedFileName);
        $this->config->shouldReceive('queriesOverrideLog')->once()->withNoArgs()->andReturn(false);
        $this->writer->save($query);
        $this->assertFileExists($this->directory);
        $this->assertCount(1, $this->filesystem->allFiles($this->directory));

        $this->assertFileExists($this->directory . '/' . $expectedFileName);
        $this->assertSame($expectedContent, file_get_contents($this->directory . '/' . $expectedFileName));
    }

    /** @test */
    public function it_doesnt_save_select_query_to_file_when_pattern_set_to_insert_or_update_queries()
    {
        $query = new SqlQuery(1, 'select * FROM test', [], 5.41);
        $this->config->shouldReceive('queriesEnabled')->once()->withNoArgs()->andReturn(true);
        $this->config->shouldReceive('queriesPattern')->once()->withNoArgs()->andReturn('#^(?:UPDATE|INSERT) .*$#i');
        $this->config->shouldReceive('queriesMinExecTime')->once()->withNoArgs()->andReturn(0);
        $this->config->shouldReceive('logDirectory')->once()->withNoArgs()->andReturn($this->directory);
        $this->writer->save($query);
        $this->assertFileExists($this->directory);
        $this->assertCount(0, $this->filesystem->allFiles($this->directory));
    }

    /** @test */
    public function it_saves_insert_query_to_file_when_pattern_set_to_insert_or_update_queries()
    {
        $expectedFileName = $this->now->format('Y-m') . '-log.sql';
        $lineContent = 'Sample log line';
        $expectedContent = "\n$lineContent\n";

        $query = new SqlQuery(1, 'INSERT INTO test(one, two, three) values(?, ?, ?)', [], 5.41);
        $this->formatter->shouldReceive('getLine')->once()->with($query)->andReturn($lineContent);
        $this->formatter->shouldReceive('getHeader')->once()->withNoArgs()->andReturn('');
        $this->config->shouldReceive('queriesEnabled')->once()->withNoArgs()->andReturn(true);
        $this->config->shouldReceive('queriesPattern')->once()->withNoArgs()->andReturn('#^(?:UPDATE|INSERT) .*$#i');
        $this->config->shouldReceive('logDirectory')->times(3)->withNoArgs()->andReturn($this->directory);
        $this->config->shouldReceive('queriesMinExecTime')->once()->withNoArgs()->andReturn(0);
        $this->filename->shouldReceive('getLogfile')->twice()->withNoArgs()->andReturn($expectedFileName);
        $this->config->shouldReceive('queriesOverrideLog')->once()->withNoArgs()->andReturn(false);
        $this->writer->save($query);
        $this->assertFileExists($this->directory);
        $this->assertCount(1, $this->filesystem->allFiles($this->directory));

        $this->assertFileExists($this->directory . '/' . $expectedFileName);
        $this->assertSame($expectedContent, file_get_contents($this->directory . '/' . $expectedFileName));
    }

    /** @test */
    public function it_uses_raw_query_without_bindings_when_using_query_pattern()
    {
        $expectedFileName = $this->now->format('Y-m') . '-log.sql';
        $lineContent = 'Sample log line';
        $expectedContent = "\n$lineContent\n";

        $query = new SqlQuery(1, 'UPDATE test SET x = ? WHERE id = ?', [2, 3], 5.41);
        $this->formatter->shouldReceive('getLine')->once()->with($query)->andReturn($lineContent);
        $this->formatter->shouldReceive('getHeader')->once()->withNoArgs()->andReturn('');
        $this->config->shouldReceive('queriesEnabled')->once()->withNoArgs()->andReturn(true);
        $this->config->shouldReceive('queriesPattern')->once()->withNoArgs()->andReturn('#^(?:UPDATE test SET x = \? |INSERT ).*$#i');
        $this->config->shouldReceive('logDirectory')->times(3)->withNoArgs()->andReturn($this->directory);
        $this->filename->shouldReceive('getLogfile')->twice()->withNoArgs()->andReturn($expectedFileName);
        $this->config->shouldReceive('queriesMinExecTime')->once()->withNoArgs()->andReturn(0);
        $this->config->shouldReceive('queriesOverrideLog')->once()->withNoArgs()->andReturn(false);
        $this->writer->save($query);
        $this->assertFileExists($this->directory);
        $this->assertCount(1, $this->filesystem->allFiles($this->directory));

        $this->assertFileExists($this->directory . '/' . $expectedFileName);
        $this->assertSame($expectedContent, file_get_contents($this->directory . '/' . $expectedFileName));
    }
}
