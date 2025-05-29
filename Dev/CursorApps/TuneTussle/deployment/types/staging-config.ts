/**
 * TuneTussle Staging Environment Configuration Types
 * Provides type safety for staging operations and configuration
 */

export interface ServerConfig {
  readonly host: string;
  readonly port: number;
  readonly user: string;
  readonly sshOptions: string[];
}

export interface StagingPaths {
  readonly staging: string;
  readonly production: string;
  readonly backups: string;
  readonly logs: string;
}

export interface StagingPorts {
  readonly backend: number;
  readonly frontend: number;
}

export interface StagingEnvironment {
  readonly server: ServerConfig;
  readonly paths: StagingPaths;
  readonly ports: StagingPorts;
}

export interface StagingOperation {
  readonly name: string;
  readonly description: string;
  readonly command: string;
  readonly requiresStaging: boolean;
}

export interface TestResult {
  readonly success: boolean;
  readonly backendTests: boolean;
  readonly frontendTests: boolean;
  readonly message: string;
  readonly timestamp: Date;
}

export interface DeploymentResult {
  readonly success: boolean;
  readonly previousVersion: string;
  readonly newVersion: string;
  readonly backupPath?: string;
  readonly message: string;
  readonly timestamp: Date;
}

// Configuration constants with type safety
export const STAGING_CONFIG: StagingEnvironment = {
  server: {
    host: "69.164.209.52",
    port: 2222,
    user: "admin",
    sshOptions: ["-o", "StrictHostKeyChecking=no", "-o", "ConnectTimeout=30"]
  },
  paths: {
    staging: "/opt/staging",
    production: "/opt/tunetussle", 
    backups: "/tmp/staging-backups",
    logs: "/var/log/staging"
  },
  ports: {
    backend: 4001,
    frontend: 3001
  }
} as const;

// Available staging operations with type safety
export const STAGING_OPERATIONS: readonly StagingOperation[] = [
  {
    name: "setup",
    description: "Copy production to staging",
    command: "staging-manager.sh setup",
    requiresStaging: false
  },
  {
    name: "test-updates", 
    description: "Test package updates in staging",
    command: "staging-manager.sh test-updates",
    requiresStaging: true
  },
  {
    name: "apply-to-prod",
    description: "Apply tested changes to production", 
    command: "staging-manager.sh apply-to-prod",
    requiresStaging: true
  },
  {
    name: "status",
    description: "Show staging status",
    command: "staging-manager.sh status", 
    requiresStaging: false
  },
  {
    name: "logs",
    description: "Show staging logs",
    command: "staging-manager.sh logs",
    requiresStaging: false
  },
  {
    name: "clean",
    description: "Clean staging environment",
    command: "staging-manager.sh clean",
    requiresStaging: false
  }
] as const;

// Type guards for runtime validation
export function isValidStagingOperation(op: string): op is StagingOperation['name'] {
  return STAGING_OPERATIONS.some(operation => operation.name === op);
}

export function getStagingOperation(name: string): StagingOperation | undefined {
  return STAGING_OPERATIONS.find(op => op.name === name);
} 