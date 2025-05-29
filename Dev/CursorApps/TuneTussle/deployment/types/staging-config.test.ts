/**
 * Tests for TuneTussle Staging Configuration Types
 * Validates type definitions and configuration constants
 */

import {
  STAGING_CONFIG,
  STAGING_OPERATIONS,
  isValidStagingOperation,
  getStagingOperation,
  type StagingEnvironment,
  type StagingOperation,
  type ServerConfig,
  type StagingPaths,
  type StagingPorts
} from './staging-config';

describe('Staging Configuration Types', () => {
  describe('STAGING_CONFIG constants', () => {
    it('should have valid server configuration', () => {
      expect(STAGING_CONFIG.server).toEqual({
        host: "69.164.209.52",
        port: 2222,
        user: "admin",
        sshOptions: ["-o", "StrictHostKeyChecking=no", "-o", "ConnectTimeout=30"]
      });
    });

    it('should have valid paths configuration', () => {
      expect(STAGING_CONFIG.paths).toEqual({
        staging: "/opt/staging",
        production: "/opt/tunetussle",
        backups: "/tmp/staging-backups",
        logs: "/var/log/staging"
      });
    });

    it('should have valid ports configuration', () => {
      expect(STAGING_CONFIG.ports).toEqual({
        backend: 4001,
        frontend: 3001
      });
    });

    it('should be readonly (immutable)', () => {
      expect(() => {
        // @ts-expect-error - Testing immutability
        STAGING_CONFIG.server.host = "different.host.com";
      }).toThrow();
    });
  });

  describe('STAGING_OPERATIONS constants', () => {
    it('should contain all expected operations', () => {
      const operationNames = STAGING_OPERATIONS.map(op => op.name);
      expect(operationNames).toEqual([
        'setup',
        'test-updates',
        'apply-to-prod',
        'status',
        'logs',
        'clean'
      ]);
    });

    it('should have proper operation configurations', () => {
      const setupOperation = STAGING_OPERATIONS.find(op => op.name === 'setup');
      expect(setupOperation).toEqual({
        name: 'setup',
        description: 'Copy production to staging',
        command: 'staging-manager.sh setup',
        requiresStaging: false
      });

      const testOperation = STAGING_OPERATIONS.find(op => op.name === 'test-updates');
      expect(testOperation).toEqual({
        name: 'test-updates',
        description: 'Test package updates in staging',
        command: 'staging-manager.sh test-updates',
        requiresStaging: true
      });
    });

    it('should be readonly array', () => {
      expect(() => {
        // @ts-expect-error - Testing immutability
        STAGING_OPERATIONS.push({
          name: 'invalid',
          description: 'Should not work',
          command: 'invalid',
          requiresStaging: false
        });
      }).toThrow();
    });
  });

  describe('isValidStagingOperation', () => {
    it('should validate correct operation names', () => {
      expect(isValidStagingOperation('setup')).toBe(true);
      expect(isValidStagingOperation('test-updates')).toBe(true);
      expect(isValidStagingOperation('apply-to-prod')).toBe(true);
      expect(isValidStagingOperation('status')).toBe(true);
      expect(isValidStagingOperation('logs')).toBe(true);
      expect(isValidStagingOperation('clean')).toBe(true);
    });

    it('should reject invalid operation names', () => {
      expect(isValidStagingOperation('invalid-operation')).toBe(false);
      expect(isValidStagingOperation('')).toBe(false);
      expect(isValidStagingOperation('Setup')).toBe(false); // Case sensitive
      expect(isValidStagingOperation('test_updates')).toBe(false); // Wrong format
    });
  });

  describe('getStagingOperation', () => {
    it('should return correct operation objects', () => {
      const setupOp = getStagingOperation('setup');
      expect(setupOp).toEqual({
        name: 'setup',
        description: 'Copy production to staging',
        command: 'staging-manager.sh setup',
        requiresStaging: false
      });

      const testOp = getStagingOperation('test-updates');
      expect(testOp).toEqual({
        name: 'test-updates',
        description: 'Test package updates in staging',
        command: 'staging-manager.sh test-updates',
        requiresStaging: true
      });
    });

    it('should return undefined for invalid operations', () => {
      expect(getStagingOperation('invalid-operation')).toBeUndefined();
      expect(getStagingOperation('')).toBeUndefined();
      expect(getStagingOperation('Setup')).toBeUndefined(); // Case sensitive
    });
  });

  describe('Type Safety', () => {
    it('should enforce ServerConfig structure', () => {
      const validConfig: ServerConfig = {
        host: "test.host.com",
        port: 22,
        user: "testuser",
        sshOptions: ["-o", "ConnectTimeout=10"]
      };
      expect(validConfig.host).toBe("test.host.com");
      expect(validConfig.port).toBe(22);
    });

    it('should enforce StagingPaths structure', () => {
      const validPaths: StagingPaths = {
        staging: "/opt/test-staging",
        production: "/opt/test-prod",
        backups: "/tmp/test-backups",
        logs: "/var/log/test"
      };
      expect(validPaths.staging).toBe("/opt/test-staging");
    });

    it('should enforce StagingPorts structure', () => {
      const validPorts: StagingPorts = {
        backend: 3000,
        frontend: 3001
      };
      expect(validPorts.backend).toBe(3000);
      expect(validPorts.frontend).toBe(3001);
    });

    it('should enforce StagingOperation structure', () => {
      const validOperation: StagingOperation = {
        name: "test-op",
        description: "Test operation",
        command: "test-command.sh",
        requiresStaging: true
      };
      expect(validOperation.name).toBe("test-op");
      expect(validOperation.requiresStaging).toBe(true);
    });

    it('should enforce complete StagingEnvironment structure', () => {
      const validEnvironment: StagingEnvironment = {
        server: {
          host: "test.host.com",
          port: 22,
          user: "testuser",
          sshOptions: ["-o", "ConnectTimeout=10"]
        },
        paths: {
          staging: "/opt/test-staging",
          production: "/opt/test-prod",
          backups: "/tmp/test-backups",
          logs: "/var/log/test"
        },
        ports: {
          backend: 3000,
          frontend: 3001
        }
      };
      expect(validEnvironment.server.host).toBe("test.host.com");
      expect(validEnvironment.paths.staging).toBe("/opt/test-staging");
      expect(validEnvironment.ports.backend).toBe(3000);
    });
  });
}); 