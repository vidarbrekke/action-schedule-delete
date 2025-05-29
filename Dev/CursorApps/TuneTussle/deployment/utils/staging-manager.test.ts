/**
 * Tests for TuneTussle Staging Manager Utility
 * Tests TypeScript staging management functionality
 */

import { StagingManager, createStagingManager, runStagingCLI } from './staging-manager';
import { execSync } from 'child_process';

// Mock child_process
jest.mock('child_process', () => ({
  execSync: jest.fn(),
  spawn: jest.fn()
}));

const mockExecSync = execSync as jest.MockedFunction<typeof execSync>;

describe('StagingManager', () => {
  let stagingManager: StagingManager;

  beforeEach(() => {
    stagingManager = new StagingManager();
    jest.clearAllMocks();
  });

  describe('isInstalled', () => {
    it('should return true when staging environment is installed', async () => {
      mockExecSync.mockReturnValue(''); // Success (no throw)
      
      const result = await stagingManager.isInstalled();
      
      expect(result).toBe(true);
      expect(mockExecSync).toHaveBeenCalledWith(
        'ssh -o StrictHostKeyChecking=no -o ConnectTimeout=30 -p 2222 admin@69.164.209.52 "test -f /usr/local/bin/staging-manager.sh"',
        { stdio: 'pipe' }
      );
    });

    it('should return false when staging environment is not installed', async () => {
      mockExecSync.mockImplementation(() => {
        throw new Error('Command failed');
      });
      
      const result = await stagingManager.isInstalled();
      
      expect(result).toBe(false);
    });
  });

  describe('executeOperation', () => {
    it('should execute valid operations successfully', async () => {
      const mockOutput = 'Operation completed successfully';
      mockExecSync.mockReturnValue(mockOutput);
      
      const result = await stagingManager.executeOperation('status');
      
      expect(result.success).toBe(true);
      expect(result.output).toBe(mockOutput);
      expect(mockExecSync).toHaveBeenCalledWith(
        'ssh -o StrictHostKeyChecking=no -o ConnectTimeout=30 -p 2222 admin@69.164.209.52 "sudo staging-manager.sh status"',
        { encoding: 'utf8', stdio: 'pipe' }
      );
    });

    it('should throw error for invalid operations', async () => {
      await expect(stagingManager.executeOperation('invalid-operation'))
        .rejects
        .toThrow('Invalid staging operation: invalid-operation');
    });

    it('should throw error when staging required but not installed', async () => {
      // Mock isInstalled to return false
      mockExecSync.mockImplementation((cmd) => {
        if (cmd.includes('test -f /usr/local/bin/staging-manager.sh')) {
          throw new Error('Not found');
        }
        return '';
      });
      
      await expect(stagingManager.executeOperation('test-updates'))
        .rejects
        .toThrow('Staging environment not installed. Run setup-server-staging.sh first.');
    });

    it('should handle command execution errors', async () => {
      const errorMessage = 'SSH connection failed';
      mockExecSync.mockImplementation(() => {
        const error = new Error(errorMessage);
        throw error;
      });
      
      const result = await stagingManager.executeOperation('status');
      
      expect(result.success).toBe(false);
      expect(result.output).toBe(errorMessage);
    });
  });

  describe('testPackageUpdates', () => {
    it('should return successful test result', async () => {
      const mockOutput = 'backend tests passed\nfrontend tests passed\nAll tests successful';
      mockExecSync.mockReturnValue(mockOutput);
      
      const result = await stagingManager.testPackageUpdates();
      
      expect(result.success).toBe(true);
      expect(result.backendTests).toBe(true);
      expect(result.frontendTests).toBe(true);
      expect(result.message).toBe('All package updates tested successfully');
      expect(result.timestamp).toBeInstanceOf(Date);
    });

    it('should handle failed tests', async () => {
      const errorMessage = 'Test failed';
      mockExecSync.mockImplementation(() => {
        const error = new Error(errorMessage);
        throw error;
      });
      
      const result = await stagingManager.testPackageUpdates();
      
      expect(result.success).toBe(false);
      expect(result.backendTests).toBe(false);
      expect(result.frontendTests).toBe(false);
      expect(result.message).toBe(errorMessage);
    });

    it('should parse partial test results correctly', async () => {
      const mockOutput = 'backend tests passed\nfrontend tests failed';
      mockExecSync.mockReturnValue(mockOutput);
      
      const result = await stagingManager.testPackageUpdates();
      
      expect(result.success).toBe(true);
      expect(result.backendTests).toBe(true);
      expect(result.frontendTests).toBe(false);
    });
  });

  describe('applyToProduction', () => {
    it('should successfully apply changes to production', async () => {
      // Mock getCurrentVersion calls
      mockExecSync
        .mockReturnValueOnce('abc123\n') // Previous version
        .mockReturnValue('Production updated successfully') // Apply operation
        .mockReturnValueOnce('def456\n'); // New version
      
      const result = await stagingManager.applyToProduction();
      
      expect(result.success).toBe(true);
      expect(result.previousVersion).toBe('abc123');
      expect(result.newVersion).toBe('def456');
      expect(result.message).toBe('Production updated successfully');
      expect(result.timestamp).toBeInstanceOf(Date);
    });

    it('should handle apply operation failures', async () => {
      mockExecSync
        .mockReturnValueOnce('abc123\n') // Previous version
        .mockImplementation(() => {
          throw new Error('Apply failed');
        });
      
      const result = await stagingManager.applyToProduction();
      
      expect(result.success).toBe(false);
      expect(result.previousVersion).toBe('abc123');
      expect(result.newVersion).toBe('abc123');
      expect(result.message).toBe('Apply failed');
    });
  });

  describe('getStatus', () => {
    it('should return status when staging is installed and running', async () => {
      const mockOutput = 'Staging environment: active\nServices running: 2/2';
      mockExecSync
        .mockReturnValueOnce('') // isInstalled check
        .mockReturnValueOnce(mockOutput); // status command
      
      const result = await stagingManager.getStatus();
      
      expect(result.installed).toBe(true);
      expect(result.running).toBe(true);
      expect(result.output).toBe(mockOutput);
    });

    it('should return not installed status', async () => {
      mockExecSync.mockImplementation(() => {
        throw new Error('Not found');
      });
      
      const result = await stagingManager.getStatus();
      
      expect(result.installed).toBe(false);
      expect(result.running).toBe(false);
      expect(result.output).toBe('Staging environment not installed');
    });

    it('should handle status command errors', async () => {
      mockExecSync
        .mockReturnValueOnce('') // isInstalled check (success)
        .mockImplementation(() => {
          throw new Error('Status command failed');
        });
      
      const result = await stagingManager.getStatus();
      
      expect(result.installed).toBe(true);
      expect(result.running).toBe(false);
      expect(result.output).toBe('Status command failed');
    });
  });

  describe('getAvailableOperations', () => {
    it('should return all available operations', () => {
      const operations = stagingManager.getAvailableOperations();
      
      expect(operations).toHaveLength(6);
      expect(operations.map(op => op.name)).toEqual([
        'setup',
        'test-updates',
        'apply-to-prod',
        'status',
        'logs',
        'clean'
      ]);
    });

    it('should return readonly array', () => {
      const operations = stagingManager.getAvailableOperations();
      
      expect(() => {
        // @ts-expect-error - Testing immutability
        operations.push({
          name: 'invalid',
          description: 'Should not work',
          command: 'invalid',
          requiresStaging: false
        });
      }).toThrow();
    });
  });
});

describe('Factory Functions', () => {
  describe('createStagingManager', () => {
    it('should create a new StagingManager instance', () => {
      const manager = createStagingManager();
      
      expect(manager).toBeInstanceOf(StagingManager);
      expect(typeof manager.isInstalled).toBe('function');
      expect(typeof manager.executeOperation).toBe('function');
    });
  });

  describe('runStagingCLI', () => {
    let consoleSpy: jest.SpyInstance;
    let processExitSpy: jest.SpyInstance;

    beforeEach(() => {
      consoleSpy = jest.spyOn(console, 'log').mockImplementation();
      processExitSpy = jest.spyOn(process, 'exit').mockImplementation();
    });

    afterEach(() => {
      consoleSpy.mockRestore();
      processExitSpy.mockRestore();
    });

    it('should show available operations when no args provided', async () => {
      await runStagingCLI([]);
      
      expect(consoleSpy).toHaveBeenCalledWith('Available operations:');
      expect(consoleSpy).toHaveBeenCalledWith('  setup: Copy production to staging');
      expect(consoleSpy).toHaveBeenCalledWith('  test-updates: Test package updates in staging');
      // ... other operations
    });

    it('should execute valid operation and exit with success', async () => {
      mockExecSync.mockReturnValue('Operation successful');
      
      await runStagingCLI(['status']);
      
      expect(consoleSpy).toHaveBeenCalledWith('Operation successful');
      expect(processExitSpy).toHaveBeenCalledWith(0);
    });

    it('should handle operation errors and exit with failure', async () => {
      const consoleErrorSpy = jest.spyOn(console, 'error').mockImplementation();
      mockExecSync.mockImplementation(() => {
        throw new Error('Operation failed');
      });
      
      await runStagingCLI(['status']);
      
      expect(consoleErrorSpy).toHaveBeenCalledWith('Error:', 'Operation failed');
      expect(processExitSpy).toHaveBeenCalledWith(1);
      
      consoleErrorSpy.mockRestore();
    });
  });
}); 