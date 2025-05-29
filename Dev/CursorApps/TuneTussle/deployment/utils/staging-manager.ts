/**
 * TuneTussle Staging Manager - TypeScript Utility
 * Provides type-safe staging operations for Node.js scripts
 */

import { execSync, spawn } from 'child_process';
import { 
  STAGING_CONFIG, 
  STAGING_OPERATIONS,
  type StagingOperation,
  type TestResult,
  type DeploymentResult,
  isValidStagingOperation,
  getStagingOperation 
} from '../types/staging-config.js';

export class StagingManager {
  private readonly config = STAGING_CONFIG;

  /**
   * Check if staging environment is installed on server
   */
  async isInstalled(): Promise<boolean> {
    try {
      const sshCmd = this.buildSSHCommand('test -f /usr/local/bin/staging-manager.sh');
      execSync(sshCmd, { stdio: 'pipe' });
      return true;
    } catch {
      return false;
    }
  }

  /**
   * Execute a staging operation
   */
  async executeOperation(operationName: string): Promise<{ success: boolean; output: string }> {
    if (!isValidStagingOperation(operationName)) {
      throw new Error(`Invalid staging operation: ${operationName}`);
    }

    const operation = getStagingOperation(operationName);
    if (!operation) {
      throw new Error(`Operation not found: ${operationName}`);
    }

    // Check if staging is required and installed
    if (operation.requiresStaging && !(await this.isInstalled())) {
      throw new Error('Staging environment not installed. Run setup-server-staging.sh first.');
    }

    try {
      const sshCmd = this.buildSSHCommand(`sudo ${operation.command}`);
      const output = execSync(sshCmd, { encoding: 'utf8', stdio: 'pipe' });
      return { success: true, output };
    } catch (error) {
      const errorOutput = error instanceof Error ? error.message : String(error);
      return { success: false, output: errorOutput };
    }
  }

  /**
   * Test package updates in staging environment
   */
  async testPackageUpdates(): Promise<TestResult> {
    const timestamp = new Date();
    
    try {
      const result = await this.executeOperation('test-updates');
      
      if (result.success) {
        // Parse output to determine individual test results
        const backendTests = result.output.includes('backend') && result.output.includes('passed');
        const frontendTests = result.output.includes('frontend') && result.output.includes('passed');
        
        return {
          success: true,
          backendTests,
          frontendTests,
          message: 'All package updates tested successfully',
          timestamp
        };
      } else {
        return {
          success: false,
          backendTests: false,
          frontendTests: false,
          message: result.output,
          timestamp
        };
      }
    } catch (error) {
      return {
        success: false,
        backendTests: false,
        frontendTests: false,
        message: error instanceof Error ? error.message : String(error),
        timestamp
      };
    }
  }

  /**
   * Apply tested changes to production
   */
  async applyToProduction(): Promise<DeploymentResult> {
    const timestamp = new Date();
    
    try {
      // Get current version info (simplified)
      const previousVersion = await this.getCurrentVersion();
      
      const result = await this.executeOperation('apply-to-prod');
      
      if (result.success) {
        const newVersion = await this.getCurrentVersion();
        
        return {
          success: true,
          previousVersion,
          newVersion,
          message: 'Production updated successfully',
          timestamp
        };
      } else {
        return {
          success: false,
          previousVersion,
          newVersion: previousVersion,
          message: result.output,
          timestamp
        };
      }
    } catch (error) {
      return {
        success: false,
        previousVersion: 'unknown',
        newVersion: 'unknown',
        message: error instanceof Error ? error.message : String(error),
        timestamp
      };
    }
  }

  /**
   * Get staging status information
   */
  async getStatus(): Promise<{ installed: boolean; running: boolean; output: string }> {
    const installed = await this.isInstalled();
    
    if (!installed) {
      return {
        installed: false,
        running: false,
        output: 'Staging environment not installed'
      };
    }

    try {
      const result = await this.executeOperation('status');
      const running = result.output.includes('active') || result.output.includes('running');
      
      return {
        installed: true,
        running,
        output: result.output
      };
    } catch (error) {
      return {
        installed: true,
        running: false,
        output: error instanceof Error ? error.message : String(error)
      };
    }
  }

  /**
   * Get available staging operations
   */
  getAvailableOperations(): readonly StagingOperation[] {
    return STAGING_OPERATIONS;
  }

  /**
   * Build SSH command with proper configuration
   */
  private buildSSHCommand(remoteCommand: string): string {
    const { server } = this.config;
    const sshOptions = server.sshOptions.join(' ');
    return `ssh ${sshOptions} -p ${server.port} ${server.user}@${server.host} "${remoteCommand}"`;
  }

  /**
   * Get current version (simplified implementation)
   */
  private async getCurrentVersion(): Promise<string> {
    try {
      const cmd = this.buildSSHCommand('cd /opt/tunetussle && git rev-parse --short HEAD');
      const output = execSync(cmd, { encoding: 'utf8', stdio: 'pipe' });
      return output.trim();
    } catch {
      return 'unknown';
    }
  }
}

/**
 * Factory function for creating staging manager instance
 */
export function createStagingManager(): StagingManager {
  return new StagingManager();
}

/**
 * CLI integration helper
 */
export async function runStagingCLI(args: string[]): Promise<void> {
  const manager = createStagingManager();
  
  if (args.length === 0) {
    console.log('Available operations:');
    manager.getAvailableOperations().forEach(op => {
      console.log(`  ${op.name}: ${op.description}`);
    });
    return;
  }
  
  const operationName = args[0];
  
  try {
    const result = await manager.executeOperation(operationName);
    console.log(result.output);
    process.exit(result.success ? 0 : 1);
  } catch (error) {
    console.error('Error:', error instanceof Error ? error.message : String(error));
    process.exit(1);
  }
} 